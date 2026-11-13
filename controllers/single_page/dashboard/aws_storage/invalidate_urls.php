<?php

namespace Concrete\Package\AwsStorage\Controller\SinglePage\Dashboard\AwsStorage;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\Exception\CloudFrontException;
use Aws\Exception\AwsException;
use Concrete\Core\Entity\File\StorageLocation\Type\Type as TypeEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use Concrete\Core\File\StorageLocation\Type\Type;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;

class InvalidateUrls extends DashboardPageController
{
    protected $helpers = [
        'form',
        'concrete/ui',
    ];
    protected TypeEntity $type;
    protected CloudFrontClient $cloudfront;
    protected StorageLocationFactory $factory;

    public function on_start()
    {
        parent::on_start();

        $config = [
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
        ];

        $this->cloudfront = new CloudFrontClient($config);

        $pkg = app()->make(PackageService::class)->getByHandle('aws_storage');
        if (!$pkg) {
            throw new UserMessageException(t('Package aws_storage not found'));
        }

        $this->type = Type::getByHandle('type_s3');
        if (!$this->type) {
            throw new UserMessageException(t('S3 storage type not available'));
        }

        $this->factory = $this->app->make(StorageLocationFactory::class);
        $defaultStorage = $this->factory->fetchDefault();
        $this->set('hideForm', $defaultStorage->getTypeObject()->fslTypeHandle !== 'type_s3');
        $this->set('defaultStorage', $defaultStorage);
    }

    public function invalidate()
    {
        if (!$this->request->isPost()) {
            $this->flash('error', t('Invalid request method'));
            return $this->buildRedirect('/dashboard/aws_storage/invalidate_urls');
        }

        $bucketName = $this->request->get('bucketName');
        $cloudfrontUrl = $this->request->get('cloudfrontUrl');
        $rawUrls = $this->request->get('urls');
        $urls = array_filter(
            array_map(
                fn ($url) => trim(str_replace($cloudfrontUrl, '', $url)),
                preg_split('/\r\n|\r|\n/', $rawUrls)
            )
        );

        $this->set('formContent', [
            'bucketName' => $bucketName,
            'cloudfrontUrl' => $cloudfrontUrl,
            'urls' => $rawUrls
        ]);

        if (empty($urls)) {
            $this->error->add(t('At least one URL required'));
        }

        $distribution = $this->findDistributionByOrigin($bucketName);
        if (!$distribution) {
            $this->error->add(t('CloudFront Distribution not found'));
        }

        if (!$this->error->has()) {
            try {
                $this->cloudfront->createInvalidation([
                    'DistributionId' => $distribution['Id'],
                    'InvalidationBatch' => [
                        'Paths' => [
                            'Quantity' => count($urls),
                            'Items' => $urls,
                        ],
                        'CallerReference' => uniqid('invalidation-', true),
                    ],
                ]);

                $this->flash('success', t('URL Invalidation sent successfully'));
                return $this->buildRedirect('/dashboard/aws_storage/invalidate_urls');
            } catch (AwsException $e) {
                $this->error->add(t('createInvalidation: AWSException - %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            } catch (CloudFrontException $e) {
                $this->error->add(t('createInvalidation: CloudFrontException - %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            }
        }
    }

    protected function findDistributionByOrigin(string $bucketName): ?array
    {
        $originDomain = "{$bucketName}.s3.eu-west-2.amazonaws.com";
        $paginator = $this->cloudfront->getPaginator('ListDistributions');

        foreach ($paginator as $page) {
            foreach ($page['DistributionList']['Items'] ?? [] as $distribution) {
                foreach ($distribution['Origins']['Items'] ?? [] as $origin) {
                    if ($origin['DomainName'] === $originDomain) {
                        return $distribution;
                    }
                }
            }
        }

        return null;
    }
}
