<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id='.(int)$this->item->id); ?>" method="post" name="adminForm" id="productgroup-form" class="form-validate">
<?php echo LayoutHelper::render('joomla.edit.title_alias',$this); ?>
<div class="row"><div class="col-lg-9"><div class="main-card p-4">
<?php echo HTMLHelper::_('uitab.startTabSet','groupTabs',['active'=>'details']); ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','details',Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
<fieldset class="options-form"><?php foreach(['axes','members','gallery','introtext','fulltext'] as $name) {echo $this->form->renderField($name);} ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php if($this->item->id): ?>
<?php echo HTMLHelper::_('uitab.addTab','groupTabs','generation',Text::_('COM_DHARMASHOP_GROUP_GENERATE')); ?>
<p><?php echo Text::_('COM_DHARMASHOP_GROUP_GENERATE_DESC'); ?></p><fieldset class="options-form"><?php echo $this->form->renderFieldset('generation');echo $this->form->renderFieldset('executions'); ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>
<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
</div></div><div class="col-lg-3"><fieldset class="options-form form-vertical"><?php echo $this->form->renderFieldset('publishing'); ?></fieldset></div></div>
<?php echo $this->form->getInput('revision'); ?>
<input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?></form>
