<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Form\FormHelper;
use Dharma\Component\DharmaShopExport\Administrator\Export\Formats;

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate')->useScript('showon');
$wa=$this->getDocument()->getWebAssetManager();$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop_export');$wa->useScript('com_dharmashop_export.profile');
$tableFormats=[];foreach((new Formats())->all() as $id=>$definition){if($definition['tabular']??true){$tableFormats[]=$id;}}
$this->getDocument()->addScriptOptions('com_dharmashop_export.profile',['tableFormats'=>$tableFormats]);
$formatSets=[];foreach((new Formats())->all() as $id=>$definition){if(!empty($definition['fieldset'])){$formatSets[$definition['fieldset']]=$id;}}
?>
<form name="adminForm" id="profile-form" class="form-validate" method="post" action="index.php?option=com_dharmashop_export&amp;view=profile&amp;layout=edit&amp;id=<?php echo (int)$this->item->id; ?>">
<?php echo HTMLHelper::_('uitab.startTabSet','export-profile',['active'=>'main','recall'=>true]); ?>
<?php foreach($this->form->getFieldsets() as $name=>$set): if(isset($formatSets[$name])){continue;} ?>
<?php echo HTMLHelper::_('uitab.addTab','export-profile',$name,Text::_($set->label??$name)); ?>
<fieldset class="options-form"><?php echo $this->form->renderFieldset($name); ?></fieldset>
<?php if($name==='main'): foreach($formatSets as $fieldset=>$format):
 $showon=FormHelper::parseShowOnConditions('format:'.$format,'jform');
 ?>
<fieldset class="options-form" data-showon='<?php echo $this->escape(json_encode($showon)); ?>'>
<legend><?php echo Text::_($this->form->getFieldsets()[$fieldset]->label??$fieldset); ?></legend>
<?php echo $this->form->renderFieldset($fieldset); ?>
</fieldset>
<?php endforeach; endif; ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endforeach; echo HTMLHelper::_('uitab.endTabSet'); ?>
<input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?></form>
