<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;
class ProductgroupsModel extends ListModel
{
 public const MEMBER_PREVIEW_LIMIT = 10;
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

 /** Attach taxonomy and a bounded composition preview only to the main list page. */
 public function getItems()
 {
  $items = parent::getItems();
  if (!$items || $this->getState('picker.modal')) { return $items; }
  $db = $this->getDatabase();
  $byId = [];
  foreach ($items as $item) {
   $item->main = null;
   $item->extra = $item->badges = $item->members = [];
   $item->brand = null;
   $byId[(int) $item->id] = $item;
  }
  $query = $db->createQuery()
   ->select($db->quoteName(['gc.group_id', 'gc.is_main', 'c.id', 'c.title', 'c.extension', 'c.params']))
   ->from($db->quoteName('#__dharmashop_group_categories', 'gc'))
   ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id=gc.catid')
   ->whereIn($db->quoteName('gc.group_id'), array_keys($byId))->order('c.lft,c.id');
  foreach ($db->setQuery($query)->loadObjectList() as $row) {
   $item = $byId[(int) $row->group_id];
   $entry = (object) ['id' => (int) $row->id, 'title' => (string) $row->title, 'extension' => (string) $row->extension];
   if ($row->extension === CategoryTypes::BRAND) { $item->brand = $entry; }
   elseif ($row->extension === CategoryTypes::BADGE) {
    $params = json_decode((string) $row->params, true) ?: [];
    $entry->color = preg_match('/^[a-z]{3,12}$/', (string) ($params['badge_color'] ?? '')) ? $params['badge_color'] : 'primary';
    $item->badges[] = $entry;
   } elseif ((int) $row->is_main === 1) { $item->main = $entry; }
   else { $item->extra[] = $entry; }
  }
  // A single round trip; each indexed group branch reads at most ten titles.
  $parts = [];
  foreach (array_keys($byId) as $id) {
   $parts[] = '(' . $db->createQuery()->select($db->quoteName(['id', 'title', 'group_id', 'created_by']))
    ->from($db->quoteName('#__dharmashop_products'))->where('group_id=' . $id)
    ->order($db->quoteName('id'))->setLimit(self::MEMBER_PREVIEW_LIMIT) . ')';
  }
  foreach ($db->setQuery(implode(' UNION ALL ', $parts))->loadObjectList() as $member) {
   $byId[(int) $member->group_id]->members[] = $member;
  }
  return $items;
 }
}
