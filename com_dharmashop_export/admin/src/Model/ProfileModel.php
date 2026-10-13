<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Model;
use Dharma\Component\DharmaShopExport\Administrator\Export\{Catalog,Formats,Plan};
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Uri\Uri;
\defined('_JEXEC') or die;
class ProfileModel extends AdminModel {
 protected $option='com_dharmashop_export';protected $text_prefix='COM_DHARMASHOP_EXPORT';public $typeAlias='com_dharmashop_export.profile';
 public function getTable($name='Profile',$prefix='Administrator',$options=[]) {return parent::getTable($name,$prefix,$options);}
 protected function loadFormData():mixed {
  $item=Factory::getApplication()->getUserState($this->option.'.edit.profile.data',[])?:$this->getItem()?:[];
  if(is_object($item) && empty($item->id)){$item->entity='products';$item->format='csv';$item->state=1;$item->params=['site_url'=>rtrim(preg_replace('~/administrator/?$~','',Uri::root()),'/')];}
  return $item;
 }
 public function getItem($pk=null) {
  $item=parent::getItem($pk);
  // Таблица отдаёт params JSON-строкой: без разбора форма открывалась бы без сохранённых настроек.
  if($item){$raw=$item->params??[];$params=is_string($raw)?(json_decode($raw,true)?:[]):(array)$raw;$entity=(string)($item->entity??'products');$params['columns_'.$entity]=$params['table_columns']??$params['columns']??[];$item->params=$params;}
  return $item;
 }
 public function getForm($data=[],$loadData=true):Form|false {
  $form=$this->loadForm($this->option.'.profile','profile',['control'=>'jform','load_data'=>$loadData]);
  if(!$form){return false;}
  foreach((new Formats())->all() as $format){if(!empty($format['form'])){$form->loadFile($format['form'],false);}}
  if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option)){$form->setFieldAttribute('state','disabled','true');$form->setFieldAttribute('state','filter','unset');}
  return $form;
 }
 public function save($data) {
  try{
   $data=array_intersect_key((array)$data,array_flip(['id','title','state','entity','format','params']));
   $id=(int)($data['id']??0);
   if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option)){$data['state']=$id?(int)$this->getItem($id)->state:1;}
   $selection='columns_'.(string)($data['entity']??'products');
   $data['params']['table_columns']=(array)($data['params'][$selection]??$data['params']['table_columns']??$data['params']['columns']??[]);
   foreach(array_keys($data['params']) as $key){if(str_starts_with((string)$key,'columns_')){unset($data['params'][$key]);}}
   $data=Plan::prepare($data,new Catalog($this->getDatabase()));
   return parent::save($data);
  }catch(\Throwable $e){$this->setError(Text::_($e->getMessage()));return false;}
 }
 public function delete(&$pks) {
  // Не удаляем профиль, пока его процесс ещё можно продолжить.
  $ids=array_filter(array_map('intval',$pks));
  if($ids && $this->getDatabase()->setQuery('SELECT id FROM #__dharmashop_export_jobs WHERE active_profile IN ('.implode(',',$ids).')',0,1)->loadResult()){$this->setError(Text::_('COM_DHARMASHOP_EXPORT_BUSY'));return false;}
  return parent::delete($pks);
 }
}
