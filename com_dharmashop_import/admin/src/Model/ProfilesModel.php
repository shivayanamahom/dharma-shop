<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Model;
\defined('_JEXEC') or die;
class ProfilesModel extends \Joomla\CMS\MVC\Model\ListModel
{
    protected $option='com_dharmashop_import';
    public function __construct($config=[]){$config['filter_fields']=['a.id','a.title','a.state'];parent::__construct($config);}
    protected function populateState($ordering='a.id',$direction='DESC'):void{parent::populateState($ordering,$direction);}
    protected function getListQuery(){
        $db=$this->getDatabase();$q=$db->createQuery()->select('a.id,a.title,a.state,a.entity,a.format,a.checked_out,a.checked_out_time')->from('#__dharmashop_import_profiles a');
        $search=trim((string)$this->getState('filter.search'));
        if($search!==''){$q->where('a.title LIKE '.$db->quote('%'.addcslashes($search,'%_\\').'%'));}
        $state=$this->getState('filter.published');if($state!==''&&$state!==null){$q->where('a.state='.(int)$state);}
        return $q->order('a.id DESC');
    }
    public function getItems(){
        $items=parent::getItems();if(!$items){return $items;}$db=$this->getDatabase();$ids=implode(',',array_map(static fn($i)=>(int)$i->id,$items));$last=[];
        foreach($db->setQuery('SELECT id,profile_id,state,created,finished,total,errors,created_count,updated_count,unchanged_count FROM #__dharmashop_import_jobs WHERE id IN (SELECT MAX(id) FROM #__dharmashop_import_jobs WHERE profile_id IN ('.$ids.') GROUP BY profile_id)')->loadAssocList() as $j){$last[(int)$j['profile_id']]=$j;}
        foreach($items as $item){$item->last=$last[(int)$item->id]??null;}return $items;
    }
    protected function getStoreId($id=''):string{return parent::getStoreId($id.':'.$this->getState('filter.search').':'.$this->getState('filter.published'));}
}
