<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;

class OrdersModel extends ListModel
{
    protected $option = 'com_dharmashop';
    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['a.id','a.number','a.created','a.total','a.status_id','status_id','customer_id'];
        parent::__construct($config);
    }
    protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
    {
        parent::populateState($ordering,$direction);
        // Ссылка из карточки покупателя задаёт фильтр и при GET; Searchtools затем сохраняет его штатно.
        $filters = Factory::getApplication()->getInput()->get('filter',[],'array');
        if (array_key_exists('customer_id',$filters)) $this->setState('filter.customer_id',max(0,(int) $filters['customer_id']));
    }
    protected function getStoreId($id = ''): string
    {
        foreach (['search','status_id','customer_id'] as $filter) $id .= ':' . $this->getState('filter.' . $filter);
        return parent::getStoreId($id);
    }
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select(['a.id','a.number','a.created','a.contact_name','a.contact_email','a.contact_phone','a.customer_id','a.user_id','a.total','a.currency','a.status_id',
            's.title AS status_title','s.color AS status_color'])
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_order_items','i') . ' WHERE i.order_id=a.id) AS items_count')
            ->from($db->quoteName('#__dharmashop_orders','a'))->join('LEFT',$db->quoteName('#__dharmashop_statuses','s') . ' ON s.id=a.status_id');
        $status = (int) $this->getState('filter.status_id');
        $customer = (int) $this->getState('filter.customer_id');
        if ($status > 0) $query->where('a.status_id=:st')->bind(':st',$status,ParameterType::INTEGER);
        if ($customer > 0) $query->where('a.customer_id=:cu')->bind(':cu',$customer,ParameterType::INTEGER);
        $search = trim((string) $this->getState('filter.search'));
        if (preg_match('/^id:\s*(\d+)$/i',$search,$match)) {
            $id = (int) $match[1]; $query->where('a.id=:id')->bind(':id',$id,ParameterType::INTEGER);
        } elseif ($search !== '') {
            $like = '%' . str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search) . '%';
            $query->where('(a.number LIKE :s1 OR a.contact_name LIKE :s2 OR a.contact_email LIKE :s3 OR a.contact_phone LIKE :s4)')
                ->bind(':s1',$like)->bind(':s2',$like)->bind(':s3',$like)->bind(':s4',$like);
        }
        $order = (string) $this->getState('list.ordering','a.id');
        $order = in_array($order,['a.id','a.number','a.created','a.total','a.status_id'],true) ? $order : 'a.id';
        $dir = strtoupper((string) $this->getState('list.direction','DESC')) === 'ASC' ? 'ASC' : 'DESC';
        return $query->order($order . ' ' . $dir)->order('a.id ' . $dir);
    }
}
