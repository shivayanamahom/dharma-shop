<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate')->useScript('showon');
?>
<form id="profile-form" name="adminForm" class="form-validate" method="post" action="index.php?option=com_dharmashop_import&amp;view=profile&amp;layout=edit&amp;id=<?php echo (int)$this->item->id; ?>">
<?php echo HTMLHelper::_('uitab.startTabSet','import-profile',['active'=>'main','recall'=>true]); ?>
<?php foreach($this->form->getFieldsets() as $name=>$set): ?>
<?php echo HTMLHelper::_('uitab.addTab','import-profile',$name,Text::_($set->label)); ?>
<fieldset class="options-form"><?php echo $this->form->renderFieldset($name); ?></fieldset>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endforeach; echo HTMLHelper::_('uitab.endTabSet'); ?>
<input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?></form>
