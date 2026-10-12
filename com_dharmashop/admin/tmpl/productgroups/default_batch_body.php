<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div class="p-3" data-dharmashop-batch-container>
    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_BATCH_HINT_PRODUCTGROUPS'); ?></p>
    <div class="row">
        <?php foreach ($this->batchForm->getFieldsets() as $name => $fieldset) : ?>
            <div class="col-lg-6 mb-3">
                <fieldset class="options-form h-100">
                    <legend><?php echo Text::_($fieldset->label); ?></legend>
                    <?php if ($fieldset->description) : ?><p class="small text-body-secondary"><?php echo Text::_($fieldset->description); ?></p><?php endif; ?>
                    <div class="form-grid"><?php echo $this->batchForm->renderFieldset($name); ?></div>
                </fieldset>
            </div>
        <?php endforeach; ?>
    </div>
<div class="btn-toolbar p-3">
    <button type="button" class="btn btn-success ms-auto" data-dharmashop-batch-task="productgroups.batch"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
</div>
</div>
