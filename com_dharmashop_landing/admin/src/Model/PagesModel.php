<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Список посадочных страниц: поиск, состояние, источник, порядок. */
class PagesModel extends ListModel
{
    protected $option = 'com_dharmashop_landing';

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
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.published') . ':' . $this->getState('filter.source'));
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select(['a.id', 'a.title', 'a.alias', 'a.catid', 'a.source', 'a.conditions', 'a.state', 'a.access', 'a.ordering', 'a.broken', 'a.checked_out', 'a.checked_out_time',
                'ag.title AS access_level', 'ed.name AS editor', 'c.title AS category_title',
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_landing_products') . ' lp WHERE lp.landing_id = a.id) AS products_count'])
            ->from($db->quoteName('#__dharmashop_landing_pages', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'ag'), 'ag.id = a.access')
            ->join('LEFT', $db->quoteName('#__users', 'ed'), 'ed.id = a.checked_out')
            ->join('LEFT', $db->quoteName('#__categories', 'c'), 'c.id = a.catid');

        $published = $this->getState('filter.published');

        if ($published !== '' && $published !== null) {
            $value = (int) $published;
            $query->where('a.state = :state')->bind(':state', $value, ParameterType::INTEGER);
        }

        $source = (string) $this->getState('filter.source');

        if (in_array($source, ['filter', 'manual'], true)) {
            $query->where('a.source = :source')->bind(':source', $source);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where('a.id = :sid')->bind(':sid', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where('(a.title LIKE :s1 OR a.alias LIKE :s2)')->bind([':s1', ':s2'], $like);
            }
        }

        $ordering = (string) $this->getState('list.ordering', 'a.ordering');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $query->order($db->escape($ordering) . ' ' . $direction);

        return $query;
    }
}
