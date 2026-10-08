<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
\defined('_JEXEC') or die;

class CustomersModel extends ListModel
{
    protected $option = 'com_dharmashop';
    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['a.id', 'a.name', 'a.email', 'a.created', 'registered'];
        parent::__construct($config);
    }
    protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }
    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.registered'));
    }
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select($db->quoteName(['a.id','a.user_id','a.name','a.email','a.phone','a.created']))
            ->select($db->quoteName('u.username', 'username'))
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_orders', 'o') . ' WHERE o.customer_id=a.id) AS orders_count')
            ->from($db->quoteName('#__dharmashop_customers', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id=a.user_id');
        $registered = (string) $this->getState('filter.registered');
        if ($registered === '1') $query->where('a.user_id IS NOT NULL');
        elseif ($registered === '0') $query->where('a.user_id IS NULL');
        $search = trim((string) $this->getState('filter.search'));
        if (preg_match('/^id:\s*(\d+)$/i', $search, $match)) {
            $id = (int) $match[1];
            $query->where('a.id=:id')->bind(':id', $id, ParameterType::INTEGER);
        } elseif ($search !== '') {
            $pattern = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%';
            $query->where('(a.name LIKE :s1 OR a.email LIKE :s2 OR a.phone LIKE :s3)')
                ->bind(':s1', $pattern)->bind(':s2', $pattern)->bind(':s3', $pattern);
        }
        $order = (string) $this->getState('list.ordering', 'a.id');
        $order = in_array($order, ['a.id','a.name','a.email','a.created'], true) ? $order : 'a.id';
        $dir = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        return $query->order($order . ' ' . $dir)->order('a.id ' . $dir);
    }
}
