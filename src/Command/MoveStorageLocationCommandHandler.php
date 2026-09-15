<?php

namespace AwsStorage\Command;

use AwsStorage\Command\MoveStorageLocationCommand;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\StorageLocation\StorageLocation;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use Core;
use Doctrine\ORM\EntityManagerInterface;

class MoveStorageLocationCommandHandler
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    public function __invoke(MoveStorageLocationCommand $command)
    {
        /** @var FileEntity $f */
        $fileEntity = $this->entityManager->find(FileEntity::class, $command->getFileID());

        /** @var StorageLocationFactory $location */
        $targetStorage = Core::make(StorageLocationFactory::class)->fetchByID($command->getStorageLocationID());

        if ($fileEntity instanceof FileEntity && $targetStorage instanceof StorageLocation) {
            $fileEntity->setFileStorageLocation($targetStorage);
        }
    }
}
