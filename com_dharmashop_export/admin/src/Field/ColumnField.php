<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Field;
use Dharma\Component\DharmaShopExport\Administrator\Export\Catalog;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class ColumnField extends ListField {
 protected $type='Column';
 protected function getOptions():array {
  $entity=(string)($this->element['entity']?:$this->form->getValue('entity',null,'products'));
  $catalog=new Catalog(Factory::getContainer()->get(DatabaseInterface::class));$options=parent::getOptions();
  // only="fields": только характеристики каталога, без служебных колонок и вариантов @alias/@id.
 $only=(string)$this->element['only'];
 foreach($catalog->schema($entity) as $key=>$col){
  // Характеристика: список, флажки, цвет, число, диапазон, короткий текст; редактор, файлы, галереи и видео в param не нужны.
  if($only==='fields'&&(!str_starts_with($key,'field.')||str_contains($key,'@')||!in_array($col['store']??'',['option','number','range','string'],true))){continue;}
  $options[]=HTMLHelper::_('select.option',$key,$col['label']);
 }
  return $options;
 }
}
