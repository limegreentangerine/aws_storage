<?php

namespace Concrete\Package\AwsStorage\File\StorageLocation\Configuration;

use Aws\S3\S3Client;
use Concrete\Core\Http\Request;
use AwsStorage\AwsS3AdapterWrapper;
use AwsStorage\Logger\AwsStorageLogger;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\File\StorageLocation\Configuration\Configuration;
use Concrete\Core\File\StorageLocation\Configuration\ConfigurationInterface;
use Concrete\Core\File\StorageLocation\Configuration\DeferredConfigurationInterface;

class TypeS3Configuration extends Configuration implements ConfigurationInterface, DeferredConfigurationInterface
{
    protected string $storageBucket = '';
    protected string $bucketUsername = '';
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
            'credentials' => [
                'key' => $this->getStorageKey(),
                'secret' => $this->getStorageSecret(),
            ],
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
     * Get the value of bucketUsername
     */
    public function getBucketUsername(): string
    {
        return $this->bucketUsername;
    }

    /**
     * Set the value of bucketUsername
     *
     * @return self
     */
    public function setBucketUsername(string $bucketUsername): self
    {
        $this->bucketUsername = $bucketUsername;

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
        $this->setBucketUsername($data['bucketUsername']);
        $this->setCloudfrontUrl($data['cloudfrontUrl']);
        $this->setStorageKey($data['storageKey']);
        $this->setStorageSecret($data['storageSecret']);
        $this->setRegion($data['region']);
        $this->setApiVersion($data['apiVersion']);
    }

    public function loadFromAwsSdk(array $credentials): void
    {
        $this->setStorageBucket($credentials['bucket']);
        $this->setBucketUsername($credentials['bucketUsername']);
        $this->setCloudfrontUrl($credentials['cloudfront_url'] ?? '');
        $this->setStorageKey($credentials['access_key']);
        $this->setStorageSecret($credentials['secret_key']);
        $this->setRegion($credentials['region']);
        $this->setApiVersion($credentials['apiVersion'] ?? $this->apiVersion);
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

        if (!$vstrings->notempty($data['storageKey'])) {
            $e->add(t('Key required'));
        }

        if (!$vstrings->notempty($data['storageSecret'])) {
            $e->add(t('Secret required'));
        }

        if (!$vstrings->notempty($data['region'])) {
            $e->add(t('Region required'));
        }

        $pkg = $app->make('Concrete\Core\Package\PackageService')->getByHandle('aws_storage');
        if ($pkg && !array_key_exists($data['region'], $pkg->getRegions())) {
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

            try {
                $client = $this->getClient();
                $client->headBucket(['Bucket' => $data['storageBucket']]);
            } catch (\Exception $ex) {
                $e->add(t('Unable to connect to S3 bucket: %s', $ex->getMessage()));
            }
        }

        return $e;
    }

    public function getAdapter(): AwsS3Adapter
    {
        try {
            $client = $this->getClient();

            // Log the adapter creation attempt
            $app = Application::getFacadeApplication();
            if ($app) {
                $log = $app->make(AwsStorageLogger::class)->getLogger();
                if ($log) {
                    $log->info('Creating S3 adapter for bucket: ' . $this->getStorageBucket());
                }
            }

            $adapter = new AwsS3AdapterWrapper(
                $client,
                $this->getStorageBucket(),
            );

            if ($app) {
                $log = $app->make(AwsStorageLogger::class)->getLogger();
                if ($log) {
                    $log->info('S3 adapter created successfully, class: ' . get_class($adapter));
                }
            }

            return $adapter;
        } catch (\Exception $e) {
            $app = Application::getFacadeApplication();
            if ($app) {
                $log = $app->make(AwsStorageLogger::class)->getLogger();
                if ($log) {
                    $log->error('Failed to create S3 adapter: ' . $e->getMessage());
                    $log->debug($e->getTraceAsString());
                }
            }
            throw new \RuntimeException('Failed to create S3 adapter: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPublicURLToFile($file): string
    {
        $app = Application::getFacadeApplication();
        if ($app) {
            $log = $app->make(AwsStorageLogger::class)->getLogger();
            if ($log) {
                $log->info('Getting public URL for file: ' . $file);
            }
        }

        $normalizedFile = ltrim((string) $file, '/');

        try {
            $url = ($this->getCloudfrontUrl() !== '' && $this->getCloudfrontUrl() !== null)
                ? rtrim($this->getCloudfrontUrl(), '/') . '/' . $normalizedFile
                : $this->getClient()->getObjectUrl($this->getStorageBucket(), $normalizedFile);

            if ($app && ($log = $app->make('log'))) {
                $log->info('Generated URL: ' . $url);
            }

            return $url;
        } catch (\Exception $e) {
            if ($app && ($log = $app->make('log'))) {
                $log->error('Failed to get public URL: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    public function getRelativePathToFile($file): string
    {
        return $file;
    }
}
