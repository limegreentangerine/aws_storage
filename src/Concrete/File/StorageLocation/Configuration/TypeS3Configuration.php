<?php

namespace Concrete\Package\AwsStorage\File\StorageLocation\Configuration;

use Aws\S3\S3Client;
use Concrete\Core\Http\Request;
use Aws\Credentials\Credentials;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\File\StorageLocation\Configuration\Configuration;
use Concrete\Core\File\StorageLocation\Configuration\ConfigurationInterface;
use Concrete\Core\File\StorageLocation\Configuration\DeferredConfigurationInterface;

class TypeS3Configuration extends Configuration implements ConfigurationInterface, DeferredConfigurationInterface
{
    protected string $storageBucket = '';
    protected string $cloudfrontUrl = '';
    protected string $storageKey = '';
    protected string $storageSecret = '';
    protected string $region = 'eu-west-2';
    protected string $apiVersion = '2006-03-01';

    protected function getClient(): S3Client
    {
        $client = new S3Client([
            'region' => $this->getRegion(),
            'version' => $this->getApiVersion(),
            'credentials' => new Credentials($this->getStorageKey(), $this->getStorageSecret()),
        ]);
        return $client;
    }

    /**
     * Get the value of storageBucket
     */
    public function getStorageBucket(): string
    {
        return $this->storageBucket;
    }

    /**
     * Set the value of storageBucket
     *
     * @return self
     */
    public function setStorageBucket(string $storageBucket): self
    {
        $this->storageBucket = $storageBucket;

        return $this;
    }

    /**
     * Get the value of cloudfrontUrl
     */
    public function getCloudfrontUrl(): string
    {
        return $this->cloudfrontUrl;
    }

    /**
     * Set the value of cloudfrontUrl
     *
     * @return self
     */
    public function setCloudfrontUrl(string $cloudfrontUrl): self
    {
        $this->cloudfrontUrl = $cloudfrontUrl;

        return $this;
    }

    /**
     * Get the value of storageKey
     */
    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    /**
     * Set the value of storageKey
     *
     * @return self
     */
    public function setStorageKey(string $storageKey): self
    {
        $this->storageKey = $storageKey;

        return $this;
    }

    /**
     * Get the value of storageSecret
     */
    public function getStorageSecret(): string
    {
        return $this->storageSecret;
    }

    /**
     * Set the value of storageSecret
     *
     * @return self
     */
    public function setStorageSecret(string $storageSecret): self
    {
        $this->storageSecret = $storageSecret;

        return $this;
    }

    /**
     * Get the value of region
     */
    public function getRegion(): string
    {
        return ($this->region !== '') ? $this->region : 'eu-west-2';
    }

    /**
     * Set the value of region
     *
     * @return self
     */
    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    /**
     * Get the value of apiVersion
     */
    public function getApiVersion(): string
    {
        return ($this->apiVersion !== '') ? $this->apiVersion : '2006-03-01';
    }

    /**
     * Set the value of apiVersion
     *
     * @return self
     */
    public function setApiVersion(string $apiVersion): self
    {
        $this->apiVersion = $apiVersion;

        return $this;
    }

    public function hasPublicURL(): bool
    {
        return true;
    }

    public function hasRelativePath(): bool
    {
        return false;
    }

    public function loadFromRequest(Request $req): void
    {
        $data = $req->get('fslType');
        $this->setStorageBucket($data['storageBucket']);
        $this->setCloudfrontUrl($data['cloudfrontUrl']);
        $this->setStorageKey($data['storageKey']);
        $this->setStorageSecret($data['storageSecret']);
        $this->setRegion($data['region']);
        $this->setApiVersion($data['apiVersion']);
    }

    public function validateRequest(Request $req)
    {
        $app = Application::getFacadeApplication();
        $e = $app->make('error');

        $vstrings = $app->make('helper/validation/strings');

        $data = $req->get('fslType');

        if (!$vstrings->notempty($data['storageBucket'])) {
            $e->add(t('Storage Bucket required'));
        }

        if (!$vstrings->notempty($data['storageBucket'])) {
            $e->add(t('Storage Bucket required'));
        }

        if (!$vstrings->notempty($data['storageKey'])) {
            $e->add(t('Key required'));
        }

        if (!$vstrings->notempty($data['storageSecret'])) {
            $e->add(t('Secret required'));
        }

        if (!$vstrings->notempty($data['region'])) {
            $e->add(t('Region required'));
        }

        $pkg = $app->make('Concrete\Core\Package\PackageService')->getByHandle('lgt_toolkit');
        if ($pkg && !in_array($data['region'], $pkg->getRegions())) {
            $e->add(t('Not a valid region'));
        }

        if (!$vstrings->notempty($data['apiVersion'])) {
            $e->add(t('API Version required'));
        }

        if (!$e->has()) {
            $this->setStorageBucket($data['storageBucket']);
            $this->setCloudfrontUrl($data['cloudfrontUrl']);
            $this->setStorageKey($data['storageKey']);
            $this->setStorageSecret($data['storageSecret']);
            $this->setRegion($data['region']);
            $this->setApiVersion($data['apiVersion']);
        }

        return $e;
    }

    public function getAdapter(): AwsS3Adapter
    {
        return new AwsS3Adapter($this->getClient(), $this->getStorageBucket());
    }

    public function getPublicURLToFile($file): string
    {
        $normalizedFile = ltrim((string) $file, '/');

        return ($this->getCloudfrontUrl() !== '' && $this->getCloudfrontUrl() !== null)
            ? rtrim($this->getCloudfrontUrl(), '/') . '/' . $normalizedFile
            : $this->getClient()->getObjectUrl($this->getStorageBucket(), $normalizedFile);
    }

    public function getRelativePathToFile($file): string
    {
        return $file;
    }
}
