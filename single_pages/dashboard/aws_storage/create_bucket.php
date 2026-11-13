<?php defined('C5_EXECUTE') or die('Access Denied.');
/**
 * @var array                                                      $regions
 * @var bool                                                       $hideForm
 * @var \Concrete\Core\View\View                                   $view
 * @var \Concrete\Core\Validation\CSRF\Token                       $token
 * @var \Concrete\Core\Form\Service\Form                           $form
 * @var \Concrete\Core\Entity\File\StorageLocation\StorageLocation $defaultStorage
 */
?>

<?php if ($hideForm) {
    /** @var Concrete\Package\AwsStorage\File\StorageLocation\Configuration\TypeS3Configuration $configuration */
    $configuration = $defaultStorage->getConfigurationObject();
    ?>
    <div class="alert alert-info"><?php echo t('An S3 Storage system is already configured: <a href="/dashboard/system/files/storage/edit/%s" target="_blank">%s</a>', $defaultStorage->getID(), $defaultStorage->getName()); ?></div>

    <fieldset>
        <legend><?php echo t('AWS Permission Issues'); ?></legend>
        <p><?php echo t('If you\'re having permission issues connecting to, or managing the bucket, click the button below. <span class="fw-bold text-danger">Use with caution</span>'); ?></p>

        <form method="post" action="<?php echo $view->action('update_policy'); ?>">
            <?php echo $token->output('update_policy_submit') ?>
            <input type="hidden" name="bucketName" value="<?php echo $configuration->getStorageBucket(); ?>" />
            <input type="hidden" name="bucketUsername" value="<?php echo $configuration->getBucketUsername(); ?>" />
            <input type="hidden" name="useCloudfront" value="<?php echo (strlen($configuration->getCloudfrontUrl()) > 0) ? 1 : 0; ?>" />
            <button type="submit" class="btn btn-danger"><?php echo t('Rebuild Policy'); ?></button>
        </form>
    </fieldset>
<?php } else { ?>
    <form method="post" action="<?php echo $view->action('create'); ?>">
        <?php echo $token->output('submit') ?>

        <div class="alert alert-info"><?php echo t('Creating an S3 storage location will make this the default'); ?></div>

        <fieldset>
            <legend><?php echo t('Bucket Settings'); ?></legend>

            <div class="form-group">
                <?php echo $form->label('bucketName', t('Bucket Name')); ?>
                <div class="input-group">
                    <?php echo $form->text('bucketName', (isset($formContent) ? $formContent['bucketName'] : ($bucketName ?? false))); ?>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>

            <div class="form-group">
                <?php echo $form->label('bucketLocation', t('Bucket Location')); ?>
                <div class="input-group">
                    <?php echo (string) $form->select('bucketLocation', $regions, (isset($formContent) ? $formContent['bucketLocation'] : ($bucketLocation ?? 'eu-west-2'))); ?>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend><?php echo t('Bucket IAM User'); ?></legend>

            <div class="form-group">
                <?php echo $form->label('bucketUsername', t('Bucket Username')); ?>
                <div class="input-group">
                    <?php echo $form->text('bucketUsername', (isset($formContent) ? $formContent['bucketUsername'] : ($bucketUsername ?? false))); ?>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>
        </fieldset>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="useCloudfront" name="useCloudfront" checked />
            <label class="form-check-label" for="useCloudfront">
                <?php echo t('Use Cloudfront Caching'); ?>
            </label>
        </div>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <?php echo $form->submit('save', t('Create Bucket'), ['class' => 'btn btn-primary float-end']); ?>
            </div>
        </div>
    </form>
<?php } ?>
