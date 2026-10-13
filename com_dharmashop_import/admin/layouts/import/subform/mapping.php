<?php
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
/** Native repeatable subform; keep new-field controls in the destination cell. */
extract($displayData);
Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('webcomponent.field-subform');
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$row=static function($form,string $group)use($fieldname,$buttons,$e):string{
    $html='<tr class="subform-repeatable-group" data-base-name="'.$e($fieldname).'" data-group="'.$e($group).'"><td>'.$form->getField('column')->renderField(['hiddenLabel'=>true]).'</td><td>'.$form->getField('target')->renderField(['hiddenLabel'=>true]);
    foreach(['field_title','field_alias','field_type'] as $name){$html.=$form->getField($name)->renderField();}
    $html.='</td><td><div class="btn-group">';
    foreach(['add'=>['success','plus','JGLOBAL_FIELD_ADD'],'remove'=>['danger','minus','JGLOBAL_FIELD_REMOVE']] as $action=>$spec){if(!empty($buttons[$action])){$html.='<button type="button" class="group-'.$action.' btn btn-sm btn-'.$spec[0].'" aria-label="'.$e(Text::_($spec[2])).'"><span class="icon-'.$spec[1].'" aria-hidden="true"></span></button>';}}
    return $html.'</div></td></tr>';
};
?>
<div class="subform-repeatable-wrapper subform-table-layout subform-table-sublayout-section">
<joomla-field-subform class="subform-repeatable subform-repeatable-table" name="<?php echo $e($name); ?>" button-add=".group-add" button-remove=".group-remove" repeatable-element=".subform-repeatable-group" rows-container="tbody.subform-repeatable-container" minimum="<?php echo (int)$min; ?>" maximum="<?php echo (int)$max; ?>">
<table class="table table-bordered"><thead><tr><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_COLUMN'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_DESTINATION'); ?></th><th><button type="button" class="group-add btn btn-sm btn-success" aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>"><span class="icon-plus" aria-hidden="true"></span></button></th></tr></thead>
<tbody class="subform-repeatable-container"><?php foreach($forms as $key=>$form){echo $row($form,$fieldname.$key);} ?></tbody></table>
<template class="subform-repeatable-template-section hidden"><?php echo $row($tmpl,$fieldname.'X'); ?></template>
</joomla-field-subform></div>
