<?php defined('C5_EXECUTE') or die('Access Denied.');
/**
 * @var bool                                                       $hideForm
 * @var \Concrete\Core\View\View                                   $view
 * @var \Concrete\Core\Validation\CSRF\Token                       $token
 * @var \Concrete\Core\Form\Service\Form                           $form
 * @var \Concrete\Core\Entity\File\StorageLocation\StorageLocation $defaultStorage
 */

/** @var Concrete\Package\AwsStorage\File\StorageLocation\Configuration\TypeS3Configuration $configuration */
$configuration = $defaultStorage->getConfigurationObject();
?>

<?php if ($hideForm || strlen($configuration->getCloudfrontUrl()) < 1) { ?>
    <div class="alert alert-danger"><?php echo t('Default storage type is not an S3 bucket or doesn\'t use CloudFront caching'); ?></div>
<?php } else { ?>
    <form method="post" action="<?php echo $view->action('invalidate'); ?>">
        <?php echo $token->output('submit_invlidation') ?>
        <?php echo $form->hidden('bucketName', (isset($formContent) ? $formContent['bucketName'] : $configuration->getStorageBucket())); ?>
        <?php echo $form->hidden('cloudfrontUrl', (isset($formContent) ? $formContent['cloudfrontUrl'] : $configuration->getCloudfrontUrl())); ?>

        <fieldset>
            <legend><?php echo t('URLs to Invalidate'); ?></legend>

            <div class="form-group">
                <?php echo $form->label('urls', t('One URL per line')); ?>

                <div class="input-group">
                    <?php echo $form->textarea('urls', (isset($formContent) ? $formContent['urls'] : false), [ 'rows' => 10, 'required' => 'required' ]); ?>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>
        </fieldset>

        <div class="alert alert-info"><?php echo t('Never invalidate all URLs, this can be costly!'); ?></div>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <?php echo $form->submit('save', t('Run Invalidation'), ['class' => 'btn btn-primary float-end']); ?>
            </div>
        </div>
    </form>
<?php } ?>
