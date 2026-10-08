<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;
class StatusesModel extends ListModel
{
    protected $option = 'com_dharmashop';
    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['a.id','a.title','a.state','a.ordering'];
        parent::__construct($config);
    }
    protected function populateState($ordering = 'a.ordering', $direction = 'ASC'): void { parent::populateState($ordering,$direction); }
    protected function getStoreId($id = ''): string { return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.state')); }
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select(['a.id','a.title','a.alias','a.color','a.is_default','a.state','a.ordering'])->from($db->quoteName('#__dharmashop_statuses','a'));
        $state = (string) $this->getState('filter.state');
        if ($state === '0' || $state === '1') { $value = (int) $state; $query->where('a.state=:st')->bind(':st',$value,\Joomla\Database\ParameterType::INTEGER); }
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') { $like = '%' . str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search) . '%'; $query->where('(a.title LIKE :s1 OR a.alias LIKE :s2)')->bind(':s1',$like)->bind(':s2',$like); }
        $order = (string) $this->getState('list.ordering','a.ordering');
        $order = in_array($order,['a.id','a.title','a.state','a.ordering'],true) ? $order : 'a.ordering';
        $direction = strtoupper((string) $this->getState('list.direction','ASC')) === 'DESC' ? 'DESC' : 'ASC';
        return $query->order($order . ' ' . $direction)->order('a.id ASC');
    }
}
