<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php if ((isset($locations) && count($locations) > 1) && isset($token) && isset($view) && isset($form)) { ?>
    <form method="post" action="<?php echo $view->action('save'); ?>">
        <?php echo $token->output('submit') ?>

        <fieldset>
            <legend><?php echo t('Move Files'); ?></legend>

            <div class="form-group">
                <?php echo $form->label('currentLocation', t('Current Location')); ?>
                <div class="input-group">
                    <select name="currentLocation" class="form-select">
                        <option value=""><?php echo t('Current Location'); ?></option>
                        <?php foreach ($locations as $location) {
                            $selected = (isset($formContent) && $formContent['currentLocation'] == $location->getID()) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $location->getID(); ?>" <?php echo $selected; ?>>
                                <?php echo $location->getDisplayName(); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>

            <div class="form-group">
                <?php echo $form->label('targetLocation', t('Target Location')); ?>
                <div class="input-group">
                    <select name="targetLocation" class="form-select">
                        <option value=""><?php echo t('Target Location'); ?></option>
                        <?php foreach ($locations as $location) {
                            $selected = (isset($formContent) && $formContent['targetLocation'] == $location->getID()) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $location->getID(); ?>" <?php echo $selected; ?>>
                                <?php echo $location->getDisplayName(); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <span class="input-group-text"><i class="fas fa-asterisk"></i></span>
                </div>
            </div>
        </fieldset>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <?php echo $form->submit('save', t('Create Queue'), array('class' => 'btn btn-primary float-end')); ?>
            </div>
        </div>
    </form>
<?php } else { ?>
    <div class="alert alert-info"><?php echo t('More than one Storage Location must be defined <a href="%s">here</a>', \URL::to('/dashboard/system/files/storage')); ?></div>
<?php } ?>
