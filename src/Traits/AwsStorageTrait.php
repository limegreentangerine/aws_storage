<?php

namespace AwsStorage\Traits;

use Exception;
use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\Iam\Exception\IamException;
use Aws\CloudFront\CloudFrontClient;
use Concrete\Core\Error\UserMessageException;
use Aws\CloudFront\Exception\CloudFrontException;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use Concrete\Core\Entity\File\StorageLocation\Type\Type as TypeEntity;
use Concrete\Package\AwsStorage\File\StorageLocation\Configuration\TypeS3Configuration;

trait AwsStorageTrait
{
    protected string $region;
    protected TypeEntity $type;
    protected S3Client $s3;
    protected IamClient $iam;
    protected CloudFrontClient $cloudfront;
    protected StorageLocationFactory $factory;
    protected string $accountId;

    /**
     * Does S3 Bucket exist
     *
     * @param  string                $bucketName
     * @return bool
     * @throws S3Exception|Exception
     */
    protected function doesBucketExist(string $bucketName)
    {
        return $this->s3->doesBucketExistV2($bucketName);
    }

    /**
     * Does User Exist
     *
     * @param  string       $bucketUsername
     * @return bool
     * @throws IamException
     */
    protected function doesUserExist(string $bucketUsername)
    {
        try {
            $this->iam->getUser([
                'UserName' => $bucketUsername,
            ]);

            return true;
        } catch (IamException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchEntity') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Does User Policy Exist
     *
     * @param  string               $bucketUsername
     * @return string|bool
     * @throws UserMessageException
     */
    protected function doesUserPolicyExist(string $bucketUsername): string|bool
    {
        $policyArn = sprintf(
            'arn:aws:iam::%s:policy/S3Access-%s',
            $this->accountId,
            $bucketUsername,
        );

        try {
            $result = $this->iam->getPolicy([
                'PolicyArn' => $policyArn,
            ]);

            return $result['Policy']['Arn'];
        } catch (IamException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchEntity') {
                return false;
            }

            throw new UserMessageException(t('doesPolicyExist: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Create User Policy
     *
     * @param  string               $bucketUsername
     * @param  array                $policy
     * @return ?string
     * @throws UserMessageException
     */
    protected function createUserPolicy(string $bucketUsername, array $policy): ?string
    {
        try {
            $result = $this->iam->createPolicy([
                'PolicyName' => 'S3Access-' . $bucketUsername,
                'PolicyDocument' => json_encode($policy),
            ]);

            return $result['Policy']['Arn'];
        } catch (AwsException $e) {
            throw new UserMessageException(t('createUserPolicy: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Attach User Policy
     *
     * @param  string               $bucketUsername
     * @param  string               $userPolicyArn
     * @throws UserMessageException
     */
    protected function attachUserPolicy(string $bucketUsername, string $userPolicyArn)
    {
        try {
            $this->iam->attachUserPolicy([
                'UserName' => $bucketUsername,
                'PolicyArn' => $userPolicyArn,
            ]);
        } catch (AwsException $e) {
            throw new UserMessageException(t('attachUserPolicy: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Get Cloudfront Access Control
     *
     * @param  string               $bucketUsername
     * @return string|bool
     * @throws UserMessageException
     */
    protected function getCloudfrontAccessControl(string $bucketUsername): string|bool
    {
        try {
            $result = $this->cloudfront->listOriginAccessControls();

            $oacName = $bucketUsername . '-oac';
            foreach ($result['OriginAccessControlList']['Items'] ?? [] as $item) {
                if ($item['Name'] === $oacName) {
                    return $item['Id'];
                }
            }

            return false;
        } catch (CloudFrontException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchOriginAccessControl') {
                return false;
            }

            throw new UserMessageException(t('getCloudfrontAccessControl: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Create Cloudfront Access Control with Policy
     *
     * @param  array                $policy
     * @return string
     * @throws UserMessageException
     */
    protected function createCloudfrontAccessControl(array $policy): ?string
    {
        try {
            $oac = $this->cloudfront->createOriginAccessControl($policy);
            return $oac['OriginAccessControl']['Id'];
        } catch (AwsException $e) {
            throw new UserMessageException(t('createCloudfrontAccessControl: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        } catch (CloudFrontException $e) {
            throw new UserMessageException(t('createCloudfrontAccessControl: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Find Cloudfront Distrubution By Origin Name
     *
     * @param  string $bucketName
     * @return ?array
     */
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

    /**
     * Create Cloudfront Distribution
     *
     * @param  string               $bucketName
     * @param  string               $bucketUsername
     * @param  string               $oacId
     * @return array
     * @throws UserMessageException
     */
    protected function createCloudfrontDistribution(string $bucketName, string $bucketUsername, string $oacId): array
    {
        try {
            $result = $this->cloudfront->createDistribution([
                'DistributionConfig' => [
                    'CallerReference' => $bucketUsername . '-' . time(),
                    'Comment' => $bucketName . ' Storage',
                    'Enabled' => true,
                    'Origins' => [
                        'Quantity' => 1,
                        'Items' => [
                            [
                                'Id' => $bucketUsername . '-origin',
                                'DomainName' => "{$bucketName}.s3.eu-west-2.amazonaws.com",
                                'OriginAccessControlId' => $oacId,
                                'S3OriginConfig' => [
                                    'OriginAccessIdentity' => '',
                                ],
                            ],
                        ],
                    ],
                    'DefaultCacheBehavior' => [
                        'TargetOriginId' => $bucketUsername . '-origin',
                        'ViewerProtocolPolicy' => 'redirect-to-https',
                        'AllowedMethods' => [
                            'Quantity' => 2,
                            'Items' => ['GET', 'HEAD'],
                            'CachedMethods' => [
                                'Quantity' => 2,
                                'Items' => ['GET', 'HEAD'],
                            ],
                        ],
                        'Compress' => true,
                        'CachePolicyId' => '658327ea-f89d-4fab-a63d-7e88639e58f6',
                    ],
                    'DefaultRootObject' => '',
                    'ViewerCertificate' => [
                        'CloudFrontDefaultCertificate' => true,
                    ],
                    'Restrictions' => [
                        'GeoRestriction' => [
                            'RestrictionType' => 'none',
                            'Quantity' => 0,
                        ],
                    ],
                ],
            ]);

            return $result->toArray();
        } catch (AwsException $e) {
            throw new UserMessageException(t('createCloudfrontDistribution: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        } catch (CloudFrontException $e) {
            throw new UserMessageException(t('createCloudfrontDistribution: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Add Concrete CMS Storage location using details
     *
     * @param  string    $bucketName
     * @param  array     $credentials
     * @throws Exception
     */
    protected function addStorageLocation(string $bucketName, array $credentials): void
    {
        /** @var TypeS3Configuration $configuration */
        $configuration = $this->type->getConfigurationObject();
        $configuration->loadFromAwsSdk($credentials);

        /** @var StorageLocationFactory $factory */
        $location = $this->factory->create($configuration, t('S3 Storage (%s)', $bucketName));
        $location->setIsDefault(1);
        $location = $this->factory->persist($location);
    }

    /**
     * Update Bucket Policy
     *
     * @param  string               $bucketName
     * @param  array                $updatedPolicy
     * @throws UserMessageException
     */
    protected function updateBucketPolicy(string $bucketName, array $updatedPolicy): void
    {
        try {
            $this->s3->putBucketPolicy([
                'Bucket' => $bucketName,
                'Policy' => json_encode($updatedPolicy),
            ]);
        } catch (AwsException $e) {
            throw new UserMessageException(t('updateBucketPolicy: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }

    /**
     * Create User Access Key
     *
     * @param  string               $bucketName
     * @param  string               $bucketUsername
     * @param  string               $bucketLocation
     * @return ?array
     * @throws UserMessageException
     */
    public function createUserAccessKey(string $bucketName, string $bucketUsername, string $bucketLocation): ?array
    {
        try {
            $keys = $this->iam->createAccessKey([
                'UserName' => $bucketUsername,
            ]);

            return [
                'access_key' => $keys['AccessKey']['AccessKeyId'],
                'secret_key' => $keys['AccessKey']['SecretAccessKey'],
                'bucket' => $bucketName,
                'region' => $bucketLocation,
            ];
        } catch (AwsException $e) {
            throw new UserMessageException(t('createAccessKey: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        } catch (IamException $e) {
            throw new UserMessageException(t('createAccessKey: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
        }
    }
}
