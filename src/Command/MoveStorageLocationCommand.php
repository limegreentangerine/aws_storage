<?php
namespace S3Storage\Command;

use Concrete\Core\File\Command\FileCommand;
use S3Storage\Command\MoveStorageLocationCommandHandler;

class MoveStorageLocationCommand extends FileCommand
{
    /**
     * @var int
     */
    protected $storageLocationID;

    public function __construct(int $fileID, int $storageLocationID)
    {
        $this->storageLocationID = $storageLocationID;
        parent::__construct($fileID);
    }

    /**
     * @return int
     */
    public function getStorageLocationID(): int
    {
        return $this->storageLocationID;
    }

    public static function getHandler(): string
    {
        return MoveStorageLocationCommandHandler::class;
    }
}
