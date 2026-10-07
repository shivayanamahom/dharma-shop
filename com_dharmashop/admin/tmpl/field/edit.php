<?php
declare(strict_types=1);
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=field&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="field-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>
    <div class="row"><div class="col-lg-9"><div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'fieldTab', ['active'=>'details','recall'=>true,'breakpoint'=>768]); ?>
        <?php echo HTMLHelper::_('uitab.addTab','fieldTab','details',Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
        <?php foreach (['required','unit'] as $name) { echo $this->form->renderField($name); } ?>
        <?php foreach (TypeRegistry::current()->all() as $type) : ?>
            <div><?php echo $this->form->renderFieldset('type_' . $type->id()); ?></div>
        <?php endforeach; ?>
        <?php echo $this->form->renderFieldset('choices'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php echo HTMLHelper::_('uitab.addTab','fieldTab','display',Text::_('COM_DHARMASHOP_TAB_DISPLAY')); ?>
        <?php foreach (['card', 'page', 'filter', 'variation'] as $context) : ?>
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_DHARMASHOP_FIELD_DISPLAY_' . strtoupper($context)); ?></legend>
                <?php if (in_array($context, ['card', 'page'], true)) : ?>
                    <?php echo $this->form->renderField('load_' . $context, 'params'); ?>
                    <?php echo $this->form->renderField($context === 'card' ? 'in_card' : 'in_page'); ?>
                    <?php echo $this->form->renderField('format_' . $context, 'params'); ?>
                <?php else : ?>
                    <?php echo $this->form->renderFieldset('display_' . $context); ?>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div></div>
    <div class="col-lg-3">
        <fieldset class="options-form form-vertical mb-3">
            <legend><?php echo Text::_('COM_DHARMASHOP_FIELD_SIDEBAR_IDENTITY'); ?></legend>
            <?php foreach (['target', 'type', 'group_id'] as $name) { echo $this->form->renderField($name); } ?>
        </fieldset>
        <fieldset class="options-form form-vertical mb-3">
            <legend><?php echo Text::_('COM_DHARMASHOP_FIELD_APPLICATION'); ?></legend>
            <?php echo $this->form->renderFieldset('application'); ?>
        </fieldset>
        <fieldset class="options-form form-vertical">
            <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
            <?php echo $this->form->renderFieldset('publishing'); ?>
        </fieldset>
    </div></div>
    <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
</form>
