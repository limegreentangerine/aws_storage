<?php

namespace Concrete\Package\AwsStorage\Controller\SinglePage\Dashboard\AwsStorage;

use Aws\S3\S3Client;
use Aws\Iam\IamClient;
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

    protected function createBucket(string $name, string $location): \Aws\Result
    {
        return $this->s3->createBucket([
            'Bucket' => $name,
            'CreateBucketConfiguration' => [
                'LocationConstraint' => $location,
            ],
        ]);
    }

    protected function getBucketPolicy(string $bucketName, string $userName)
    {
        $policy = [
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

        try {
            $result = $this->iam->createPolicy([
                'PolicyName' => 'S3Access-' . $userName,
                'PolicyDocument' => json_encode($policy),
            ]);

            return $result['Policy']['Arn'];
        } catch (\Aws\Exception\AwsException $e) {
            $this->error->add($e->getAwsErrorMessage() ?? $e->getMessage(), $e->getAwsErrorCode());
            return false;
        }
    }

    protected function addStorageLocation(string $bucketName, array $credentials)
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

        $pkg = app()->make(PackageService::class)->getByHandle('aws_storage');
        if (!$pkg) {
            throw new UserMessageException(t('Package aws_storage not found'));
        }
        $this->set('regions', $pkg->getRegions());

        $this->type = Type::getByHandle('type_s3');
        if (!$this->type) {
            throw new UserMessageException(t('S3 storage type not available'));
        }
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
        $this->validate($bucketName, $bucketLocation, $bucketUsername);

        $this->set('formContent', [
            'bucketName' => $bucketName,
            'bucketLocation' => $bucketLocation,
            'bucketUsername' => $bucketUsername,
        ]);

        if (!$this->error->has()) {
            try {
                $this->createBucket($bucketName, $bucketLocation);
                $this->createUser($bucketUsername);
                $bucketPolicy = $this->getBucketPolicy($bucketName, $bucketUsername);
                if ($bucketPolicy) {
                    $this->iam->attachUserPolicy([
                        'UserName' => $bucketUsername,
                        'PolicyArn' => $bucketPolicy,
                    ]);

                    $keys = $this->iam->createAccessKey([
                        'UserName' => $bucketUsername,
                    ]);

                    $credentials = [
                        'access_key' => $keys['AccessKey']['AccessKeyId'],
                        'secret_key' => $keys['AccessKey']['SecretAccessKey'],
                        'bucket' => $bucketName,
                        'region' => $bucketLocation,
                    ];

                    $this->addStorageLocation($credentials['bucket'], $credentials);

                    $this->flash('success', t('Bucket created for %s', $bucketName));
                    return $this->buildRedirect('/dashboard/aws_storage/create_bucket');
                }
            } catch (\Aws\Exception\AwsException $e) {
                $this->error->add($e->getAwsErrorMessage() ?? $e->getMessage(), $e->getAwsErrorCode());
            }
        }
    }

    public function createUser(string $username): \Aws\Result
    {
        return $this->iam->createUser([
            'UserName' => $username,
            'Path' => '/s3-storage/',
        ]);
    }
}
