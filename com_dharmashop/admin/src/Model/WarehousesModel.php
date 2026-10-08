<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

class WarehousesModel extends ListModel
{
    protected $option = 'com_dharmashop';

    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['a.id', 'a.title', 'a.state', 'a.ordering'];
        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.published'));
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select(['a.id', 'a.title', 'a.alias', 'a.address', 'a.phone', 'a.lat', 'a.lon', 'a.state', 'a.access', 'a.ordering', 'a.checked_out', 'a.checked_out_time', 'ag.title AS access_level', 'ed.name AS editor'])
            ->from($db->quoteName('#__dharmashop_warehouses', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'ag'), 'ag.id = a.access')
            ->join('LEFT', $db->quoteName('#__users', 'ed'), 'ed.id = a.checked_out');

        $state = (string) $this->getState('filter.published');

        if ($state === '0' || $state === '1') {
            $value = (int) $state;
            $query->where('a.state = :st')->bind(':st', $value, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where('(a.title LIKE :s1 OR a.alias LIKE :s2 OR a.address LIKE :s3)')->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like);
        }

        $order     = (string) $this->getState('list.ordering', 'a.ordering');
        $order     = in_array($order, ['a.id', 'a.title', 'a.state', 'a.ordering'], true) ? $order : 'a.ordering';
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        return $query->order($order . ' ' . $direction)->order('a.id ASC');
    }
}
