<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
$wa->useScript('keepalive')->useScript('form.validate')->useScript('com_dharmashop.group-members');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id='.(int)$this->item->id); ?>" method="post" name="adminForm" id="productgroup-form" class="form-validate">
<?php echo LayoutHelper::render('joomla.edit.title_alias',$this); ?>
<div class="row"><div class="col-lg-9"><div class="main-card p-4">
<?php echo HTMLHelper::_('uitab.startTabSet','groupTabs',['active'=>'details']); ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','details',Text::_('COM_DHARMASHOP_GROUP_TAB_PRODUCTS')); ?>
<fieldset class="options-form"><?php echo $this->form->renderField('axes'); ?></fieldset>
<section class="mt-4 ps-3" aria-labelledby="group-members-title">
<h2 id="group-members-title" class="h4 mb-3"><?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS'); ?></h2>
<?php echo $this->form->getInput('members'); ?>
</section>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','content',Text::_('COM_DHARMASHOP_GROUP_TAB_CONTENT')); ?>
<fieldset class="options-form"><?php echo $this->form->renderFieldset('content'); ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php if($this->item->id): ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','generation',Text::_('COM_DHARMASHOP_GROUP_GENERATE')); ?>
<p><?php echo Text::_('COM_DHARMASHOP_GROUP_GENERATE_DESC'); ?></p><fieldset class="options-form"><?php echo $this->form->renderFieldset('generation');echo $this->form->renderFieldset('executions'); ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>
<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
</div></div><div class="col-lg-3"><fieldset class="options-form form-vertical mb-3"><legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_CATEGORIES'); ?></legend><?php echo $this->form->renderFieldset('taxonomy'); ?></fieldset><fieldset class="options-form form-vertical"><?php echo $this->form->renderFieldset('publishing'); ?></fieldset></div></div>
<?php echo $this->form->getInput('revision'); ?>
<input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?></form>
