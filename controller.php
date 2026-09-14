<?php

namespace Concrete\Package\S3Storage;

use Core;
use S3Storage\Package\PageTrait;
use Concrete\Core\Package\Package;
use S3Storage\Package\StorageTypeTrait;

class Controller extends Package
{
    use PageTrait;
    use StorageTypeTrait;

    /**
     * The packages handle.
     * Note that this must be unique in the
     * entire concrete5 package ecosystem.
     *
     * @var string
     */
    protected $pkgHandle = 's3_storage';

    /**
     * The packages version.
     *
     * @var string
     */
    protected $pkgVersion = '1.0.0-beta.1';

    /**
     * The minimum Concrete version compatible with the package.
     * Override this value according to the minimum required version for your package.
     *
     * @var string
     */
    protected $appVersionRequired = '9.5.0';

    /**
     * The minimum PHP version compatible with the package.
     * Override this value according to the minimum required version for your package.
     *
     * @var string
     * @var string
     */
    protected $phpVersionRequired = '8.4';

    /**
     * Package service providers to register.
     *
     * eg. 'Concrete\Package\PackageHandle\Src\Providers\PackageServiceProvider'
     *
     * @var array
     */
    protected $providers = [];

    /**
     * An array describing the package dependencies.
     * Keys are package handles.
     * Values may be:
     * - false: this package can't be installed if the other package is already installed.
     * - true: this package can't be installed of the other package is not installed
     * - a string: this package can't be installed of the other package is not installed or it's installed with an older version
     * - an array with two strings, representing the minimum and the maximum version of the other package to be installed.
     *
     * @var array
     *
     * @example [
     *     // This package can't be installed if a package with handle other_package_1 is already installed.
     *     'other_package_1' => false,
     *     // This package can't be installed if a package with handle other_package_2 is not installed.
     *     'other_package_2' => true,
     *     // This package can't be installed if a package with handle other_package_3 is not installed, or it has a version prior to 1.0
     *     'other_package_3' => '1.0',
     *     // This package can't be installed if a package with handle other_package_4 is not installed, or it has a version prior to 2.0, or it has a version after 2.9
     *     'other_package_4' => ['2.0', '2.9'],
     * ]
     */
    protected $packageDependencies = [];

    /**
     * Package class autoloader registrations
     * The package install helper class, included with this boilerplate,
     * is activated by default.
     *
     * @see https://goo.gl/4wyRtH
     * @var array
     */
    protected $pkgAutoloaderRegistries = [
        'src' => '\S3Storage',
    ];

    /**
     * Package tasks to register.
     *
     * eg. 'task_handle' => \PackageHandle\Command\Task\Controller\TaskHandleController::class,
     *
     * @var array
     */
    protected $tasks = [];

    /**
     * S3 Storage Regions
     *
     * @var array
     */
    protected static $regions = [
        '' => 'Choose one...',
        'us-east-1' => 'US East (N. Virginia)',
        'us-east-2' => 'US East (Ohio)',
        'us-west-1' => 'US West (N. California)',
        'us-west-2' => 'US West (Oregon)',
        'af-south-1' => 'Africa (Cape Town)',
        'ap-east-1' => 'Asia Pacific (Hong Kong)',
        'ap-east-2' => 'Asia Pacific (Taipei)',
        'ap-south-1' => 'Asia Pacific (Mumbai)',
        'ap-south-2' => 'Asia Pacific (Hyderabad)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-southeast-3' => 'Asia Pacific (Jakarta)',
        'ap-southeast-4' => 'Asia Pacific (Melbourne)',
        'ap-southeast-5' => 'Asia Pacific (Malaysia)',
        'ap-southeast-6' => 'Asia Pacific (New Zealand)',
        'ap-southeast-7' => 'Asia Pacific (Thailand)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-northeast-3' => 'Asia Pacific (Osaka)',
        'ca-central-1' => 'Canada (Central)',
        'ca-west-1' => 'Canada West (Calgary)',
        'eu-central-1' => 'Europe (Frankfurt)',
        'eu-central-2' => 'Europe (Zurich)',
        'eu-west-1' => 'Europe (Ireland)',
        'eu-west-2' => 'Europe (London)',
        'eu-west-3' => 'Europe (Paris)',
        'eu-north-1' => 'Europe (Stockholm)',
        'eu-south-1' => 'Europe (Milan)',
        'eu-south-2' => 'Europe (Spain)',
        'il-central-1' => 'Israel (Tel Aviv)',
        'me-south-1' => 'Middle East (Bahrain)',
        'me-central-1' => 'Middle East (UAE)',
        'mx-central-1' => 'Mexico (Central)',
        'sa-east-1' => 'South America (São Paulo)',
    ];

    protected function installOrUpgrade(\Concrete\Core\Entity\Package $pkg): void
    {
        $this->addStorageType('type_s3', $pkg, 'S3 Storage');
        $this->addSinglePage('/dashboard/s3_storage', $pkg, t('S3 Storage'));
    }

    public function getPackageName()
    {
        return t('S3 Storage');
    }

    public function getPackageDescription()
    {
        return t('Adds S3 Storage options to ConcreteCMS');
    }

    /**
     * The packages install routine.
     */
    public function install()
    {
        $pkg = parent::install();
        $this->installDatabase();
        $this->installOrUpgrade($pkg);
    }

    /**
     * The packages upgrade routine.
     */
    public function upgrade()
    {
        $pkg = Core::make('Concrete\Core\Package\PackageService')->getByHandle($this->pkgHandle);
        parent::upgrade();
        $this->installOrUpgrade($pkg);
    }

    public function getRegions()
    {
        return $this->regions;
    }
}
