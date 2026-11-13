<?php

namespace Concrete\Package\AwsStorage\Controller\SinglePage\Dashboard\AwsStorage;

use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\S3\Exception\AwsException;
use Aws\Iam\Exception\IamException;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\Exception\S3Exception;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\StorageLocation\Type\Type;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use Concrete\Core\Entity\File\StorageLocation\Type\Type as TypeEntity;
use Concrete\Package\AwsStorage\File\StorageLocation\Configuration\TypeS3Configuration;

class CreateBucket extends DashboardPageController
{
    protected $helpers = [
        'form',
        'concrete/ui',
    ];
    protected string $region;
    protected TypeEntity $type;
    protected S3Client $s3;
    protected IamClient $iam;
    protected CloudFrontClient $cloudfront;

    protected function getBucketPolicy(string $bucketName, string $userName): ?string
    {
        $policy = $this->getBasePolicyArray($bucketName);

        try {
            $result = $this->iam->createPolicy([
                'PolicyName' => 'S3Access-' . $userName,
                'PolicyDocument' => json_encode($policy),
            ]);

            return $result['Policy']['Arn'];
        } catch (\Aws\Exception\AwsException $e) {
            $this->error->add($e->getAwsErrorMessage() ?? $e->getMessage(), $e->getAwsErrorCode());
            return null;
        }
    }

    protected function addStorageLocation(string $bucketName, array $credentials): void
    {
        /** @var TypeS3Configuration $configuration */
        $configuration = $this->type->getConfigurationObject();
        $configuration->loadFromAwsSdk($credentials);

        $factory = $this->app->make(StorageLocationFactory::class);

        /* @var StorageLocationFactory $factory */
        $location = $factory->create($configuration, t('S3 Storage (%s)', $bucketName));
        $location->setIsDefault(1);
        $location = $factory->persist($location);
    }

    protected function validate(string $bucketName, string $bucketLocation, string $bucketUsername): void
    {
        $vstrings = app()->make('helper/validation/strings');

        if (!$vstrings->notempty($bucketName)) {
            $this->error->add(t('Bucket Name required'));
        }

        if (!$vstrings->notempty($bucketLocation)) {
            $this->error->add(t('Bucket Location required'));
        }

        if (!$vstrings->notempty($bucketUsername)) {
            $this->error->add(t('Bucket Username required'));
        }
    }

    protected function getBasePolicyArray(string $bucketName): array
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:ListBucket',
                    ],
                    'Resource' => "arn:aws:s3:::{$bucketName}",
                ],
                [
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:GetObject',
                        's3:PutObject',
                        's3:DeleteObject',
                    ],
                    'Resource' => "arn:aws:s3:::{$bucketName}/*",
                ],
            ],
        ];
    }

    protected function createCloudfrontAccessControl(string $bucketName, string $bucketUsername): \Aws\Result
    {
        return $this->cloudfront->createOriginAccessControl([
            'OriginAccessControlConfig' => [
                'Name' => $bucketUsername . '-oac',
                'Description' => 'Access control for ' . $bucketName . ' bucket',
                'SigningProtocol' => 'sigv4',
                'SigningBehavior' => 'always',
                'OriginAccessControlOriginType' => 's3',
            ],
        ]);
    }

    protected function createCloudfrontDistribution(string $bucketName, string $bucketUsername, string $oacId): \Aws\Result
    {

        return $this->cloudfront->createDistribution([
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
    }

    protected function updateBucketPolicy(string $bucketName, string $distributionArn): \Aws\Result
    {
        $policy = array_merge($this->getBasePolicyArray($bucketName), [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'AllowCloudFrontAccess',
                    'Effect' => 'Allow',
                    'Principal' => [
                        'Service' => 'cloudfront.amazonaws.com',
                    ],
                    'Action' => 's3:GetObject',
                    'Resource' => "arn:aws:s3:::{$bucketName}/*",
                    'Condition' => [
                        'StringEquals' => [
                            'AWS:SourceArn' => $distributionArn,
                        ],
                    ],
                ],
            ],
        ]);

        return $this->s3->putBucketPolicy([
            'Bucket' => $bucketName,
            'Policy' => json_encode($policy),
        ]);
    }

    protected function getUser(string $bucketUsername): bool
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

    protected function checkPolicyExists(string $arn): bool
    {
        try {
            $result = $this->iam->getPolicy([
                'PolicyArn' => $arn
            ]);

            var_dump_safe($result);
            die;

            return true;

        } catch (IamException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchEntity') {
                return false;
            }

            throw $e;
        }
    }

    public function on_start()
    {
        parent::on_start();

        $config = [
            'region' => 'eu-west-2',
            'version' => 'latest',
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
        ];

        $this->s3 = new S3Client($config);
        $this->iam = new IamClient($config);
        $this->cloudfront = new CloudFrontClient([
            ...$config,
            'region' => 'us-east-1',
        ]);

        $pkg = app()->make(PackageService::class)->getByHandle('aws_storage');
        if (!$pkg) {
            throw new UserMessageException(t('Package aws_storage not found'));
        }
        $this->set('regions', $pkg->getRegions());

        $this->type = Type::getByHandle('type_s3');
        if (!$this->type) {
            throw new UserMessageException(t('S3 storage type not available'));
        }

        // TODO: get current storage locations and check for type_s3, I don't want more than one of these for now
    }

    public function create()
    {
        if (!$this->request->isPost()) {
            $this->error->add(t('Invalid request method'));
        }

        if (!$this->token->validate('submit')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $bucketName = $this->request->get('bucketName');
        $bucketLocation = $this->request->get('bucketLocation');
        $bucketUsername = $this->request->get('bucketUsername');
        $useCloudfront = ($this->request->get('useCloudfront')) ? true : false;
        $this->validate($bucketName, $bucketLocation, $bucketUsername);

        $this->set('formContent', [
            'bucketName' => $bucketName,
            'bucketLocation' => $bucketLocation,
            'bucketUsername' => $bucketUsername,
            'useCloudfront' => $useCloudfront
        ]);

        if (!$this->error->has()) {
            // create bucket
            try {
                if (!$this->s3->doesBucketExistV2($bucketName)) {
                    $this->s3->createBucket([
                        'Bucket' => $bucketName,
                        'CreateBucketConfiguration' => [
                            'LocationConstraint' => $bucketLocation,
                        ],
                    ]);
                }
            } catch (AwsException $e) {
                $this->error->add(t('createBucket: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            } catch (S3Exception $e) {
                $this->error->add(t('doesBucketExist: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            }

            // create user
            try {
                if (!$this->getUser($bucketUsername)) {
                    return $this->iam->createUser([
                        'UserName' => $bucketUsername,
                        'Path' => '/s3-storage/',
                    ]);
                }
            } catch (AwsException $e) {
                $this->error->add(t('createUser: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            } catch (IamException $e) {
                $this->error->add(t('getUser: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            }


            // create bucket policy
            $bucketPolicy = false;
            // try {
            //     $bucketPolicy = $this->checkPolicyExists($bucketUsername);

            //     if ($bucketPolicy === false) {
            //         $bucketPolicy = $this->getBucketPolicy($bucketName, $bucketUsername);
            //     }
            // } catch (AwsException $e) {
            //     $this->error->add(t('getBucketPolicy: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            // } catch (IamException $e) {
            //     $this->error->add(t('checkPolicyExists: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
            // }

            if ($bucketPolicy !== false) {
                try {
                    $this->iam->attachUserPolicy([
                        'UserName' => $bucketUsername,
                        'PolicyArn' => $bucketPolicy,
                    ]);
                } catch (AwsException $e) {
                    $this->error->add(t('attachUserPolicy: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
                }

                $credentials = [];
                try {
                    $keys = $this->iam->createAccessKey([
                        'UserName' => $bucketUsername,
                    ]);

                    $credentials = [
                        'access_key' => $keys['AccessKey']['AccessKeyId'],
                        'secret_key' => $keys['AccessKey']['SecretAccessKey'],
                        'bucket' => $bucketName,
                        'region' => $bucketLocation,
                    ];
                } catch (AwsException $e) {
                    $this->error->add(t('createAccessKey: %s', $e->getAwsErrorMessage() ?? $e->getMessage()), $e->getAwsErrorCode());
                }

                if (!empty($credentials)) {
                    die('useCloudfront');
                    if ($useCloudfront) {
                        $oac = $this->createCloudfrontAccessControl($bucketName, $bucketUsername);
                        $oacId = $oac['OriginAccessControl']['Id'];

                        $distribution = $this->createCloudfrontDistribution($bucketName, $bucketUsername, $oacId);
                        $credentials['cloudfront_url'] = 'https://' . $distribution['Distribution']['DomainName'];
                        $distributionArn = $distribution['Distribution']['ARN'];

                        $this->updateBucketPolicy($bucketName, $distributionArn);
                    }

                    $this->addStorageLocation($credentials['bucket'], $credentials);

                    $this->flash('success', t('Bucket created for %s', $bucketName));
                    return $this->buildRedirect('/dashboard/aws_storage/create_bucket');
                }
                $this->error->add(t('Empty credentials array'));

            }
        }
    }
}
