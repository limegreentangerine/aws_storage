<?php

namespace Concrete\Package\AwsStorage\Controller\SinglePage\Dashboard\AwsStorage;

use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Sts\StsClient;
use Aws\S3\Exception\AwsException;
use Aws\CloudFront\CloudFrontClient;
use AwsStorage\Traits\AwsPolicyTrait;
use AwsStorage\Traits\AwsStorageTrait;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\StorageLocation\Type\Type;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\File\StorageLocation\StorageLocationFactory;
use Concrete\Core\Entity\File\StorageLocation\Type\Type as TypeEntity;

class CreateBucket extends DashboardPageController
{
    use AwsStorageTrait;
    use AwsPolicyTrait;

    protected $helpers = [
        'form',
        'concrete/ui',
    ];
    protected string $region;
    protected TypeEntity $type;
    protected S3Client $s3;
    protected IamClient $iam;
    protected CloudFrontClient $cloudfront;
    protected StorageLocationFactory $factory;
    protected string $accountId;

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

    protected function validateUpdate(string $bucketName, string $bucketUsername): void
    {
        $vstrings = app()->make('helper/validation/strings');

        if (!$vstrings->notempty($bucketName)) {
            $this->error->add(t('Bucket Name required'));
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
        $this->cloudfront = new CloudFrontClient([
            ...$config,
            'region' => 'us-east-1',
        ]);

        $sts = new StsClient($config);
        $identity = $sts->getCallerIdentity();
        $this->accountId = $identity['Account'];

        $pkg = app()->make(PackageService::class)->getByHandle('aws_storage');
        if (!$pkg) {
            throw new UserMessageException(t('Package aws_storage not found'));
        }
        $this->set('regions', $pkg->getRegions());

        $this->type = Type::getByHandle('type_s3');
        if (!$this->type) {
            throw new UserMessageException(t('S3 storage type not available'));
        }

        $this->factory = $this->app->make(StorageLocationFactory::class);
        $defaultStorage = $this->factory->fetchDefault();
        $this->set('hideForm', $defaultStorage->getTypeObject()->fslTypeHandle === 'type_s3');
        $this->set('defaultStorage', $defaultStorage);
    }

    public function create()
    {
        if (!$this->request->isPost()) {
            $this->error->add(t('Invalid request method'));
        }

        if (!$this->token->validate('submit')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $th = $this->app->make('helper/text');

        $bucketName = $this->request->get('bucketName');
        $bucketLocation = $this->request->get('bucketLocation');
        $bucketUsername = $th->urlify($this->request->get('bucketUsername'));
        $useCloudfront = ($this->request->get('useCloudfront')) ? true : false;
        $this->validate($bucketName, $bucketLocation, $bucketUsername);

        $this->set('formContent', [
            'bucketName' => $bucketName,
            'bucketLocation' => $bucketLocation,
            'bucketUsername' => $bucketUsername,
            'useCloudfront' => $useCloudfront,
        ]);

        if ($this->doesBucketExist($bucketName)) {
            $this->error->add(t('A bucket exists with the name %s, if this is intentional you will need to configure the storage location manually.', $bucketName));
        }

        if ($this->doesUserExist($bucketUsername)) {
            $this->error->add(t('A user exists with the name %s, if this is intentional you will need to configure the storage location manually.', $bucketUsername));
        }

        // simple validation done, now page will throw exceptions as these are bigger issues....
        if (!$this->error->has()) {
            // create bucket
            try {
                $this->s3->createBucket([
                    'Bucket' => $bucketName,
                    'CreateBucketConfiguration' => [
                        'LocationConstraint' => $bucketLocation,
                    ],
                ]);
            } catch (AwsException $e) {
                throw new UserMessageException(t('createBucket: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
            }

            // create user
            try {
                $user = $this->iam->createUser([
                    'UserName' => $bucketUsername,
                    'Path' => '/s3-storage/',
                ]);
                $userArn = $user['User']['Arn'];
            } catch (AwsException $e) {
                throw new UserMessageException(t('createUser: %s', $e->getAwsErrorMessage() ?? $e->getMessage()));
            }

            // create user policy and attach
            $userPolicyArn = $this->doesUserPolicyExist($bucketUsername);
            if ($userPolicyArn === false) {
                $userPolicyArr = $this->getUserPolicy($bucketName);
                $userPolicyArn = $this->createUserPolicy($bucketUsername, $userPolicyArr);
            }
            $this->attachUserPolicy($bucketUsername, $userPolicyArn);

            // create access keys
            $credentials = $this->createUserAccessKey(
                $bucketName,
                $bucketUsername,
                $bucketLocation,
            );

            sleep(1); // wait just a sec, let aws catch up....

            if ($useCloudfront) {
                // we are using cloudfront
                // try and get access control, if there isn't one make it
                $oacId = $this->getCloudfrontAccessControl($bucketUsername);

                if ($oacId === false) {
                    $cloudfrontPolicy = $this->getCloudfrontAccessControlPolicy($bucketName, $bucketUsername);
                    $oacId = $this->createCloudfrontAccessControl($cloudfrontPolicy);
                }

                // try and get distribution, if there isn't one make it and attach the access control policy
                $distribution = $this->findDistributionByOrigin($bucketName);
                if (!$distribution) {
                    $distribution = $this->createCloudfrontDistribution($bucketName, $bucketUsername, $oacId);
                }

                $credentials['cloudfront_url'] = 'https://' . $distribution['Distribution']['DomainName'];
                $distributionArn = $distribution['Distribution']['ARN'];

                // update the buckets policy so cloudfront can access it
                $updatedBucketPolicy = $this->getBucketPolicy($this->accountId, $bucketName, $userArn, $distributionArn);
                $this->updateBucketPolicy($bucketName, $updatedBucketPolicy);
            } else {
                // create base bucket policy and attach
                $bucketPolicy = $this->getBucketPolicy($this->accountId, $bucketName, $userArn);
                $this->updateBucketPolicy($bucketName, $bucketPolicy);
            }

            // create the storage location is c5
            $credentials['bucketUsername'] = $bucketUsername;
            $this->addStorageLocation($bucketName, $credentials);

            // tada! it should be done with a host of annoying errors
            $this->flash('success', t('Bucket created for %s', $bucketName));
            return $this->buildRedirect('/dashboard/aws_storage/create_bucket');
        }
    }

    public function update_policy()
    {
        if (!$this->request->isPost()) {
            $this->error->add(t('Invalid request method'));
        }

        if (!$this->token->validate('update_policy_submit')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $bucketName = $this->request->get('bucketName');
        $bucketUsername = $this->request->get('bucketUsername');
        $useCloudfront = (bool) $this->request->get('useCloudfront');
        $this->validateUpdate($bucketName, $bucketUsername);

        $distribution = null;
        if ($useCloudfront) {
            $distribution = $this->findDistributionByOrigin($bucketName);

            if (!$distribution) {
                $this->error->add(t('No distribution found for %s', $bucketName));
            }
        }

        if (!$this->error->has()) {
            $policy = $this->getBucketPolicy($this->accountId, $bucketName, $bucketUsername, ($useCloudfront && $distribution) ? $distribution['ARN'] : null);
            $this->updateBucketPolicy($bucketName, $policy);

            $this->flash('success', t('Policy updated for bucket: %s', $bucketName));
            return $this->buildRedirect('/dashboard/aws_storage/create_bucket');
        }
    }
}
