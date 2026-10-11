<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
foreach (['COM_DHARMASHOP_GENERATOR_COUNT', 'COM_DHARMASHOP_GENERATOR_CLEAR_CONFIRM'] as $key) { Text::script($key); }
$wa->useScript('keepalive')->useScript('form.validate')->useScript('com_dharmashop.group-members')->useScript('com_dharmashop.group-generator')->useScript('webcomponent.toolbar-button');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id='.(int)$this->item->id); ?>" method="post" name="adminForm" id="productgroup-form" class="form-validate">
<?php echo LayoutHelper::render('joomla.edit.title_alias',$this); ?>
<div class="row"><div class="col-lg-9"><div class="main-card p-4">
<?php echo HTMLHelper::_('uitab.startTabSet','groupTabs',['active'=>'details']); ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','details',Text::_('COM_DHARMASHOP_GROUP_TAB_PRODUCTS')); ?>
<fieldset class="options-form"><?php echo $this->form->renderField('axes'); ?></fieldset>
<section class="mt-4 ps-3" aria-labelledby="group-members-title">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<h2 id="group-members-title" class="h4 mb-0"><?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS'); ?></h2>
<button type="button" class="btn btn-outline-danger" data-group-members-clear-all data-confirm="<?php echo htmlspecialchars(Text::_('COM_DHARMASHOP_GROUP_MEMBERS_CLEAR_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>" disabled>
<span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS_CLEAR_ALL'); ?>
</button>
</div>
<?php echo $this->form->getInput('members'); ?>
</section>
<?php if ($this->getCurrentUser()->authorise('core.create', 'com_dharmashop')) : ?>
<section class="border-top mt-4 pt-4" data-group-generator aria-labelledby="group-generator-title">
    <h2 id="group-generator-title" class="h4"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_HEADING'); ?></h2>
    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_GROUP_GENERATE_DESC'); ?></p>
    <?php echo $this->form->renderField('copy_fields'); ?>
    <p class="small text-body-secondary"><?php echo Text::sprintf('COM_DHARMASHOP_GROUP_COMMON_FIELDS_COUNT', $this->commonFieldCount); ?></p>
    <div class="border rounded bg-body-tertiary p-3 form-vertical">
        <?php echo $this->form->renderField('title_template'); ?>
        <div class="d-flex flex-wrap gap-2 mb-3" aria-label="<?php echo Text::_('COM_DHARMASHOP_GENERATOR_TOKENS'); ?>">
            <button type="button" class="btn btn-sm btn-outline-primary" data-generator-token="{group.title}"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_TOKEN_GROUP'); ?></button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-generator-token="{variant.values}"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_TOKEN_VALUES'); ?></button>
            <?php foreach ($this->form->getGroup('generation_values') as $field) : ?>
            <button type="button" class="btn btn-sm btn-outline-primary" data-generator-token="{field.<?php echo (int) substr($field->fieldname, 2); ?>}"><?php echo htmlspecialchars($field->title, ENT_QUOTES, 'UTF-8'); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="h5 mb-0"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_VALUES'); ?></h3>
            <joomla-toolbar-button task="productgroup.generationFields" form="#productgroup-form" form-validation><button type="button" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_REFRESH'); ?></button></joomla-toolbar-button>
        </div>
        <p class="text-body-secondary small" data-generator-axes-notice hidden><?php echo Text::_('COM_DHARMASHOP_GENERATOR_AXES_CHANGED'); ?></p>
        <div class="row g-3">
            <?php foreach ($this->form->getGroup('generation_values') as $field) : ?>
            <div class="col-md-6"><?php echo $field->renderField(); ?></div>
            <?php endforeach; ?>
        </div>
        <details class="mt-2">
            <summary class="mb-3"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_DEFAULTS'); ?></summary>
            <div class="row g-3">
                <div class="col-12"><?php echo $this->form->renderField('generation_catid'); ?></div>
                <?php foreach (['generation_price', 'generation_stock', 'generation_state'] as $name) : ?>
                <div class="col-sm-4"><?php echo $this->form->renderField($name); ?></div>
                <?php endforeach; ?>
            </div>
        </details>
        <joomla-toolbar-button task="productgroup.prepare" form="#productgroup-form" form-validation><button type="button" class="btn btn-primary mt-2" data-generator-prepare><span class="icon-list" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_GENERATOR_PREPARE'); ?></button></joomla-toolbar-button>
    </div>
    <h3 class="h5 mt-4"><?php echo Text::_('COM_DHARMASHOP_GROUP_EXECUTIONS'); ?></h3>
    <?php echo $this->form->getInput('executions'); ?>
    <div class="border-top pt-3 mt-3 d-flex flex-wrap align-items-center gap-3">
        <joomla-toolbar-button task="productgroup.generate" form="#productgroup-form" form-validation><button type="button" class="btn btn-success" data-generator-create disabled><span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_GROUP_GENERATE'); ?></button></joomla-toolbar-button>
        <span class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_CREATE_DESC'); ?></span>
    </div>
</section>
<?php endif; ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','content',Text::_('COM_DHARMASHOP_GROUP_TAB_CONTENT')); ?>
<fieldset class="options-form"><?php echo $this->form->renderFieldset('content'); ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php echo LayoutHelper::render('field-tabs', ['form' => $this->form, 'groups' => $this->fieldGroups, 'tabSet' => 'groupTabs', 'hint' => 'COM_DHARMASHOP_GROUP_COMMON_FIELDS_HINT'], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>
<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
</div></div><div class="col-lg-3"><fieldset class="options-form form-vertical mb-3"><legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_CATEGORIES'); ?></legend><?php echo $this->form->renderFieldset('taxonomy'); ?></fieldset><fieldset class="options-form form-vertical"><?php echo $this->form->renderFieldset('publishing'); ?></fieldset></div></div>
<?php echo $this->form->getInput('revision'); ?>
<input type="hidden" name="generation_complete" value="1"><input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?></form>
