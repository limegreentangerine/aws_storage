<?php
namespace Concrete\Package\S3Storage\Controller\SinglePage\Dashboard;

use FileList;
use Exception;
use Concrete\Core\Command\Batch\Batch;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\StorageLocation\StorageLocation;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use S3Storage\Command\MoveStorageLocationCommand;

class S3Storage extends DashboardPageController
{
    protected $helpers = [
        'form',
        'concrete/ui'
    ];

    protected StorageLocationFactory $storageLocationFactory;

    public function on_start()
    {
        parent::on_start();

        $this->storageLocationFactory = $this->app->make(StorageLocationFactory::class);

        $this->set('locations', $this->storageLocationFactory->fetchList());
    }

    public function save()
    {
        if ($this->post()) {
            $post = $this->post();
            $this->set('formContent', $post);

            if (!$this->token->validate('submit')) {
                $this->error->add($this->token->getErrorMessage());
            }

            $this->validate($post);

            if (!$this->error->has()) {
                // get storage locations
                $currentStorage = $this->storageLocationFactory->fetchByID($post['currentLocation']);
                $targetStorage  = $this->storageLocationFactory->fetchByID($post['targetLocation']);

                if (!$currentStorage) {
                    throw new Exception(t('Current Storage Location (ID: %s) not found', $post['currentLocation']), 404);
                }

                if (!$targetStorage) {
                    throw new Exception(t('Target Storage Location (ID: %s) not found', $post['targetLocation']), 404);
                }

                // get list of all files
                $files = $this->getFiles($currentStorage);

                if (count($files) > 0) {
                    $batch = Batch::create();
                    $batch->setName('Move Files to new Storage Location');

                    foreach ($files as $index => $file) {
                        if ($file instanceof FileEntity) {
                            $versions = $file->getFileVersions();
                            foreach ($versions as $version) {
                                if ($version->isApproved()) continue;

                                $path = $_SERVER['DOCUMENT_ROOT'] . $version->getRelativePath();
                                if (!file_exists($path)) {
                                    $version->delete();
                                }
                            }
                            $batch->add(new MoveStorageLocationCommand($file->getFileID(), $targetStorage->getID()));
                        }
                    }

                    $this->dispatchBatch($batch);
                    $this->buildRedirect('/dashboard/system/automation/activity')->send();
                }
            }
        } else {
            $this->buildRedirect('/dashboard/s3_storage')->send();
        }
    }

    protected function validate(array $args): void
    {
        $vnumbers = $this->app->make('helper/validation/numbers');

        if (!$vnumbers->integer($args['currentLocation'])) {
            $this->error->add(t('Please choose a location'), 'currentLocation');
        }

        if (!$vnumbers->integer($args['targetLocation'])) {
            $this->error->add(t('Please choose a target location'), 'targetLocation');
        }
    }

    protected function getFiles(StorageLocation $currentStorage)
    {
        $fl = new FileList();
        $fl->filterByStorageLocation($currentStorage);
        return $fl->getResults();
    }
}
