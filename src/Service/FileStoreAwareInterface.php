<?php declare(strict_types=1);

namespace DerivativeMedia\Service;

use Omeka\File\Store\StoreInterface;

/**
 * Interface for services that require file store injection.
 * 
 * This interface defines the contract for services that need access to
 * Omeka's file storage system for reading and writing files.
 */
interface FileStoreAwareInterface
{
    /**
     * Set the file store instance.
     *
     * @param StoreInterface $fileStore The file store to inject
     * @return void
     */
    public function setFileStore(StoreInterface $fileStore): void;
}
