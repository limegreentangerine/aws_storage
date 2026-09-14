<?php

namespace S3Storage\Package;

use Concrete\Core\File\StorageLocation\Type\Type as StorageType;

trait StorageTypeTrait
{
    /**
     * Add Remote Storage
     * @param string $handle Storage Type Handle
     * @param object $pkg Package Object
     * @param string $name Storage Type Name
     * @return object Storage Type Object
     */
    protected function addStorageType($handle, $pkg, $name)
    {
        $st = StorageType::getByHandle($handle);
        if (!is_object($st)) {
            StorageType::add($handle, $name, $pkg);
        }

        return $st;
    }
}
