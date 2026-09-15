<?php defined('C5_EXECUTE') or die('Access Denied');
    $form = \Core::make('helper/form');
    $pkg = \Core::make('Concrete\Core\Package\PackageService')->getByHandle('aws_storage');
    $regions = $pkg->getRegions();
?>

<div class="form-group">
    <?php echo $form->label('storageBucket', t('Storage Bucket')); ?>

    <div class="input-group">
        <?php echo $form->text('fslType[storageBucket]', (isset($configuration) && is_object($configuration)) ? $configuration->getStorageBucket() : false, [ 'required' => 'required' ]); ?>
        <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
    </div>
</div>

<div class="form-group">
    <?php echo $form->label('cloudfrontUrl', t('Cloudfront URL <small>(optional)</small>')); ?>
    <?php echo $form->text('fslType[cloudfrontUrl]', (isset($configuration) && is_object($configuration)) ? $configuration->getCloudfrontUrl() : false); ?>
</div>

<div class="form-group">
    <?php echo $form->label('storageKey', t('Access Key')); ?>

    <div class="input-group">
        <?php echo $form->text('fslType[storageKey]', (isset($configuration) && is_object($configuration)) ? $configuration->getStorageKey() : false, [ 'required' => 'required' ]); ?>
        <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
    </div>
</div>

<div class="form-group">
    <?php echo $form->label('storageSecret', t('Access Secret')); ?>

    <div class="input-group">
        <?php echo $form->text('fslType[storageSecret]', (isset($configuration) && is_object($configuration)) ? $configuration->getStorageSecret() : false, [ 'required' => 'required' ]); ?>
        <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
    </div>
</div>

<div class="form-group">
    <?php echo $form->label('region', t('Amazon S3 Region')); ?>
    <?php echo $form->select('fslType[region]', $regions, (isset($configuration) && is_object($configuration)) ? $configuration->getRegion(): false); ?>
</div>

<div class="form-group">
    <?php echo $form->label('apiVersion', t('API Version')); ?>
    <?php echo $form->text('fslType[apiVersion]', (isset($configuration) && is_object($configuration)) ? $configuration->getApiVersion() : '2006-03-01'); ?>
</div>
