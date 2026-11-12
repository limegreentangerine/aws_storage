<?php defined('C5_EXECUTE') or die('Access Denied.');
/**
 * @var \Concrete\Core\View\View             $view
 * @var \Concrete\Core\Validation\CSRF\Token $token
 * @var \Concrete\Core\Form\Service\Form     $form
 * @var array                                $regions
 */
?>

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

    <fieldset>
        <legend><?php echo t('Cloudfront Settings'); ?></legend>
        TODO
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?php echo $form->submit('save', t('Create Bucket'), ['class' => 'btn btn-primary float-end']); ?>
        </div>
    </div>
</form>
