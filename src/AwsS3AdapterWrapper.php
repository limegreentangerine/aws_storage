<?php

namespace AwsStorage;

use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Concrete\Core\Support\Facade\Application;

/**
 * Wrapper around AwsS3Adapter to add logging for debugging and handle ACL-disabled buckets
 */
class AwsS3AdapterWrapper extends AwsS3Adapter
{
    private function log(string $level, string $message)
    {
        try {
            $app = Application::getFacadeApplication();
            if ($app) {
                $log = $app->make('log');
                if ($log) {
                    $log->{$level}($message);
                }
            }
        } catch (\Exception $e) {
            // Silently fail if logging doesn't work
        }
    }

    /**
     * Handle upload without ACL for ACL-disabled buckets
     */
    private function uploadWithoutACL(string $path, mixed $body, Config $config)
    {
        $key = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        // Remove ACL from options
        unset($options['ACL']);

        if (!$this->isOnlyDir($path)) {
            if (!isset($options['ContentType'])) {
                $options['ContentType'] = Util::guessMimeType($path, $body);
            }

            if (!isset($options['ContentLength'])) {
                $options['ContentLength'] = is_resource($body) ? Util::getStreamSize($body) : Util::contentSize($body);
            }

            if ($options['ContentLength'] === null) {
                unset($options['ContentLength']);
            }
        }

        try {
            // Use PutObject directly without ACL for ACL-disabled buckets
            $putParams = array_merge(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $body], $options);
            $this->s3Client->putObject($putParams);
        } catch (\Aws\S3\Exception\S3MultipartUploadException $multipartUploadException) {
            return false;
        } catch (\Exception $e) {
            // For ACL errors, try without any ACL-related parameters
            if (strpos($e->getMessage(), 'AccessControlListNotSupported') !== false) {
                try {
                    $this->s3Client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $body] + $options);
                } catch (\Exception $e2) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return $this->normalizeResponse($options, $path);
    }

    private function isOnlyDir(string $path)
    {
        return substr($path, -1) === '/';
    }

    public function write($path, $contents, Config $config)
    {
        $this->log('info', "AwsS3Adapter::write() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $contents, $config);
            $this->log('info', "AwsS3Adapter::write() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::write() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function writeStream($path, $resource, Config $config)
    {
        $this->log('info', "AwsS3Adapter::writeStream() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $resource, $config);
            $this->log('info', "AwsS3Adapter::writeStream() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::writeStream() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function put(string $path, mixed $contents, Config $config)
    {
        $this->log('info', "AwsS3Adapter::put() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $contents, $config);
            $this->log('info', "AwsS3Adapter::put() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::put() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function putStream(string $path, mixed $resource, Config $config)
    {
        $this->log('info', "AwsS3Adapter::putStream() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $resource, $config);
            $this->log('info', "AwsS3Adapter::putStream() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::putStream() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function update($path, $contents, Config $config)
    {
        $this->log('info', "AwsS3Adapter::update() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $contents, $config);
            $this->log('info', "AwsS3Adapter::update() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::update() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function updateStream($path, $resource, Config $config)
    {
        $this->log('info', "AwsS3Adapter::updateStream() called with path: $path");
        try {
            $result = $this->uploadWithoutACL($path, $resource, $config);
            $this->log('info', "AwsS3Adapter::updateStream() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::updateStream() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function copy($path, $newpath)
    {
        $this->log('info', "AwsS3Adapter::copy() called: $path -> $newpath");
        try {
            $result = parent::copy($path, $newpath);
            $this->log('info', "AwsS3Adapter::copy() succeeded: $path -> $newpath");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::copy() failed: $path -> $newpath - " . $e->getMessage());
            throw $e;
        }
    }

    public function rename($path, $newpath)
    {
        $this->log('info', "AwsS3Adapter::rename() called: $path -> $newpath");
        try {
            $result = parent::rename($path, $newpath);
            $this->log('info', "AwsS3Adapter::rename() succeeded: $path -> $newpath");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::rename() failed: $path -> $newpath - " . $e->getMessage());
            throw $e;
        }
    }

    public function delete($path)
    {
        $this->log('info', "AwsS3Adapter::delete() called with path: $path");
        try {
            $result = parent::delete($path);
            $this->log('info', "AwsS3Adapter::delete() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::delete() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function has($path)
    {
        $this->log('info', "AwsS3Adapter::has() called with path: $path");
        try {
            $result = parent::has($path);
            $this->log('info', 'AwsS3Adapter::has() result: ' . ($result ? 'true' : 'false'));
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::has() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function read($path)
    {
        $this->log('info', "AwsS3Adapter::read() called with path: $path");
        try {
            $result = parent::read($path);
            $this->log('info', "AwsS3Adapter::read() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::read() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }

    public function readStream($path)
    {
        $this->log('info', "AwsS3Adapter::readStream() called with path: $path");
        try {
            $result = parent::readStream($path);
            $this->log('info', "AwsS3Adapter::readStream() succeeded for path: $path");
            return $result;
        } catch (\Exception $e) {
            $this->log('error', "AwsS3Adapter::readStream() failed for path: $path - " . $e->getMessage());
            throw $e;
        }
    }
}
