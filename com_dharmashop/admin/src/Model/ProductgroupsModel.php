<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;
class ProductgroupsModel extends ListModel
{
 protected $option='com_dharmashop';
 public function __construct($config=[]) {$config['filter_fields']=['id','g.id','title','g.title','state','g.state','published'];parent::__construct($config);}
 protected function populateState($ordering='g.id',$direction='DESC'): void {parent::populateState($ordering,$direction);}
 protected function getListQuery(): QueryInterface
 {
  $db=$this->getDatabase();$q=$db->createQuery()->select('g.id,g.title,g.alias,g.state,g.members_count,g.checked_out,g.checked_out_time')->from('#__dharmashop_product_groups g');
  $state=$this->getState('filter.published');$q->where(is_numeric($state)?'g.state='.(int)$state:'g.state IN (0,1)');
  $search=trim((string)$this->getState('filter.search'));if($search!=='') {$q->where('g.title LIKE '.$db->quote('%'.$db->escape($search,true).'%'));}
  $order=in_array($this->getState('list.ordering'),['g.id','g.title','g.state'],true)?$this->getState('list.ordering'):'g.id';
  return $q->order($order.(strtoupper((string)$this->getState('list.direction'))==='ASC'?' ASC':' DESC'))->order('g.id');
 }
 protected function getStoreId($id=''): string {return parent::getStoreId($id.':'.$this->getState('filter.search').':'.$this->getState('filter.published'));}
}
