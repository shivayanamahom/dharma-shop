<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;
class ProductgroupsModel extends ListModel
{
 protected $option='com_dharmashop';
 public function __construct($config=[]) {$config['filter_fields']=['id','g.id','title','g.title','state','g.state','published'];parent::__construct($config);}
 protected function populateState($ordering='g.id',$direction='DESC'): void {
  // The picker has independent search, pagination and status preferences.
  $modal = Factory::getApplication()->getInput()->getCmd('layout') === 'modal';
  if ($modal) { $this->context .= '.modal'; $this->filterFormName = 'filter_productgroups_modal'; }
  parent::populateState($ordering,$direction);
  $this->setState('picker.modal', $modal);
 }
 protected function getListQuery(): QueryInterface
 {
  $db=$this->getDatabase();$q=$db->createQuery()->select('g.id,g.title,g.alias,g.state,g.members_count,g.checked_out,g.checked_out_time,g.cover,g.price_min,g.price_max')->from('#__dharmashop_product_groups g');
  $state=$this->getState('filter.published');
  $allowedState = is_numeric($state) && (!$this->getState('picker.modal') || in_array((int) $state, [0, 1], true));
  $q->where($allowedState ? 'g.state='.(int)$state : 'g.state IN (0,1)');
  if ($this->getState('picker.modal')) { $q->where('g.members_count<' . \Dharma\Component\DharmaShop\Administrator\Catalog\GroupService::MEMBER_LIMIT); }
  $search=trim((string)$this->getState('filter.search'));if($search!=='') {$q->where('g.title LIKE '.$db->quote('%'.$db->escape($search,true).'%'));}
  $order=in_array($this->getState('list.ordering'),['g.id','g.title','g.state'],true)?$this->getState('list.ordering'):'g.id';
  return $q->order($order.(strtoupper((string)$this->getState('list.direction'))==='ASC'?' ASC':' DESC'))->order('g.id');
 }
 protected function getStoreId($id=''): string {return parent::getStoreId($id.':'.$this->getState('filter.search').':'.$this->getState('filter.published'));}
}
