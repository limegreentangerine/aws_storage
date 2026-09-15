<?php

namespace Tests;

use Concrete\Package\AwsStorage\File\StorageLocation\Configuration\TypeS3Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeS3Configuration::class)]
class TypeS3ConfigurationTest extends TestCase
{
    public function testDefaultValuesAreAppliedWhenUnset(): void
    {
        $configuration = new TypeS3Configuration();

        $this->assertSame('eu-west-2', $configuration->getRegion());
        $this->assertSame('2006-03-01', $configuration->getApiVersion());
    }

    public function testSettersAndGettersKeepConfiguredValues(): void
    {
        $configuration = (new TypeS3Configuration())
            ->setStorageBucket('my-bucket')
            ->setCloudfrontUrl('https://cdn.example.com/')
            ->setStorageKey('test-key')
            ->setStorageSecret('test-secret')
            ->setRegion('us-east-1')
            ->setApiVersion('2024-01-01');

        $this->assertSame('my-bucket', $configuration->getStorageBucket());
        $this->assertSame('https://cdn.example.com/', $configuration->getCloudfrontUrl());
        $this->assertSame('test-key', $configuration->getStorageKey());
        $this->assertSame('test-secret', $configuration->getStorageSecret());
        $this->assertSame('us-east-1', $configuration->getRegion());
        $this->assertSame('2024-01-01', $configuration->getApiVersion());
        $this->assertTrue($configuration->hasPublicURL());
        $this->assertFalse($configuration->hasRelativePath());
    }

    public function testPublicUrlPrefersCloudfrontWhenConfigured(): void
    {
        $configuration = (new TypeS3Configuration())
            ->setCloudfrontUrl('https://cdn.example.com/')
            ->setStorageBucket('my-bucket')
            ->setStorageKey('test-key')
            ->setStorageSecret('test-secret')
            ->setRegion('us-east-1');

        $this->assertSame(
            'https://cdn.example.com/path/to/file.jpg',
            $configuration->getPublicURLToFile('/path/to/file.jpg')
        );
    }

    public function testPublicUrlFallsBackToS3WhenCloudfrontIsEmpty(): void
    {
        $configuration = (new TypeS3Configuration())
            ->setCloudfrontUrl('')
            ->setStorageBucket('my-bucket')
            ->setStorageKey('test-key')
            ->setStorageSecret('test-secret')
            ->setRegion('us-east-1');

        $url = $configuration->getPublicURLToFile('/path/to/file.jpg');

        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('my-bucket', $url);
        $this->assertStringContainsString('path/to/file.jpg', $url);
    }

    public function testGetAdapterCreatesAwsS3Adapter(): void
    {
        $configuration = (new TypeS3Configuration())
            ->setStorageBucket('my-bucket')
            ->setStorageKey('test-key')
            ->setStorageSecret('test-secret')
            ->setRegion('us-east-1');

        $this->assertInstanceOf(\League\Flysystem\AwsS3v3\AwsS3Adapter::class, $configuration->getAdapter());
    }
}
