<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Model;
use Dharma\Component\DharmaShopExport\Administrator\Export\{Files,Formats};
use Joomla\CMS\MVC\Model\ListModel;
\defined('_JEXEC') or die;
class ProfilesModel extends ListModel {
 protected $option='com_dharmashop_export';
 public function __construct($config=[]) {$config['filter_fields']=['a.id','a.title','a.state'];parent::__construct($config);}
 protected function populateState($ordering='a.id',$direction='DESC'):void {parent::populateState($ordering,$direction);}
 protected function getListQuery() {
  $db=$this->getDatabase();$q=$db->createQuery()->select('a.id,a.title,a.entity,a.format,a.state,a.checked_out,a.checked_out_time,a.params')->from('#__dharmashop_export_profiles a');
  $search=trim((string)$this->getState('filter.search'));
  if($search!==''){$q->where('a.title LIKE '.$db->quote('%'.addcslashes($search,'%_\\').'%'));}
  $state=$this->getState('filter.published');if($state!=='' && $state!==null){$q->where('a.state='.(int)$state);}
  $order=(string)$this->getState('list.ordering','a.id');if(!in_array($order,['a.id','a.title','a.state'],true)){$order='a.id';}
  return $q->order($order.($this->getState('list.direction')==='ASC'?' ASC':' DESC'));
 }
 /** К элементам добавляются `last` (последний готовый файл: запуск, время, размер, публичная ссылка) и `active` (запуск, который можно продолжить). */
 public function getItems() {
  $items=parent::getItems();if(!$items){return $items;}
  $db=$this->getDatabase();$ids=implode(',',array_map(fn($i)=>(int)$i->id,$items));
  $last=[];foreach($db->setQuery("SELECT j.id,j.profile_id,j.finished,j.bytes,j.snapshot FROM #__dharmashop_export_jobs j WHERE j.id IN (SELECT MAX(id) FROM #__dharmashop_export_jobs WHERE state='done' AND artifact IS NOT NULL AND profile_id IN (".$ids.") GROUP BY profile_id)")->loadAssocList() as $j){
   $s=json_decode((string)$j['snapshot'],true)?:[];
   $last[(int)$j['profile_id']]=['id'=>(int)$j['id'],'finished'=>$j['finished'],'bytes'=>(int)$j['bytes'],'public'=>($s['profile']['params']['visibility']??'')==='public'&&$s?Files::publicLink($s):''];
  }
  $active=[];foreach($db->setQuery('SELECT profile_id,id,state FROM #__dharmashop_export_jobs WHERE active_profile IN ('.$ids.')')->loadAssocList() as $j){$active[(int)$j['profile_id']]=$j;}
  $formats=(new Formats())->all();
  foreach($items as $item){$item->feed=!empty($formats[$item->format]['public']);$item->last=$last[(int)$item->id]??null;$item->active=$active[(int)$item->id]??null;unset($item->params);}
  return $items;
 }
 protected function getStoreId($id=''):string {return parent::getStoreId($id.':'.$this->getState('filter.search').':'.$this->getState('filter.published'));}
}
