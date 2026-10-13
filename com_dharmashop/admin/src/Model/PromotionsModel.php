<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;
class PromotionsModel extends ListModel
{
    protected $option='com_dharmashop';
    public function __construct($config=[]){$config['filter_fields']??=['a.id','a.title','a.plugin','a.state','a.ordering'];parent::__construct($config);}
    protected function getListQuery(): QueryInterface
    {
        $db=$this->getDatabase();$codeList='(SELECT GROUP_CONCAT(pc.code ORDER BY pc.ordering,pc.id SEPARATOR ", ") FROM #__dharmashop_promotion_codes pc WHERE pc.promotion_id=a.id)';
        $q=$db->createQuery()->select(['a.id','a.title','a.plugin','a.state','a.ordering','a.access','a.params',$codeList.' AS codes'])->from($db->quoteName('#__dharmashop_promotions','a'));
        $state=(string)$this->getState('filter.published');if($state==='0'||$state==='1'){$stateValue=(int)$state;$q->where('a.state=:st')->bind(':st',$stateValue,ParameterType::INTEGER);}
        $search=trim((string)$this->getState('filter.search'));if($search!==''){$like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';$q->where('(a.title LIKE :s OR EXISTS (SELECT 1 FROM #__dharmashop_promotion_codes pc WHERE pc.promotion_id=a.id AND pc.code LIKE :c))')->bind(':s',$like)->bind(':c',$like);}
        $order=(string)$this->getState('list.ordering','a.ordering');$order=in_array($order,['a.id','a.title','a.plugin','a.state','a.ordering'],true)?$order:'a.ordering';$dir=strtoupper((string)$this->getState('list.direction','ASC'))==='DESC'?'DESC':'ASC';return $q->order($order.' '.$dir)->order('a.id DESC');
    }
}
