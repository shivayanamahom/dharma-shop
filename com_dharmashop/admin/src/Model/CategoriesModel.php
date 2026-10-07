<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Список категорий одного типа (категории, бренды или бейджи — тип задаёт параметр extension; типы между собой не смешиваются).
 * Для каждой записи добавляет число товаров по витринному индексу.
 */
class CategoriesModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'published', 'a.published', 'lft', 'a.lft', 'level'];
        }

        parent::__construct($config);
    }

    /** Тип, с которым работает список (из запроса, проверенный). */
    public function getExtension(): string
    {
        return CategoryTypes::normalize(Factory::getApplication()->getInput()->getString('extension', CategoryTypes::CATEGORY));
    }

    protected function populateState($ordering = 'a.lft', $direction = 'ASC'): void
    {
        $this->setState('filter.extension', $this->getExtension());

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        foreach (['filter.extension', 'filter.search', 'filter.published', 'filter.level'] as $key) {
            $id .= ':' . $this->getState($key);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery();
        $extension = (string) $this->getState('filter.extension');

        $query->select($db->quoteName([
            'a.id', 'a.title', 'a.alias', 'a.note', 'a.level', 'a.parent_id', 'a.lft', 'a.rgt', 'a.published', 'a.access',
            'a.extension', 'a.params', 'a.checked_out', 'a.checked_out_time', 'a.created_user_id',
        ]))
            ->select($db->quoteName('ag.title', 'access_level'))
            ->select($db->quoteName('uc.name', 'editor'))
            ->from($db->quoteName('#__categories', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'ag') . ' ON ag.id = a.access')
            ->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON uc.id = a.checked_out')
            ->where($db->quoteName('a.extension') . ' = :extension')
            ->bind(':extension', $extension);

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.published') . ' = :published')->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '' || $published === null) {
            $query->whereIn($db->quoteName('a.published'), [0, 1]);
        }

        $level = (int) $this->getState('filter.level');

        if ($level > 0) {
            $query->where($db->quoteName('a.level') . ' <= :level')->bind(':level', $level, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.alias') . ' LIKE :s2 OR ' . $db->quoteName('a.note') . ' LIKE :s3)')
                    ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like);
            }
        }

        $orderCol = (string) $this->state->get('list.ordering', 'a.lft');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $allowed = ['a.lft', 'a.published', 'a.title', 'a.id'];
        $orderCol = in_array($orderCol, $allowed, true) ? $orderCol : 'a.lft';
        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }

    /** @return object[] записи с полями count_total, count_published (товаров в категории и её подкатегориях) */
    public function getItems()
    {
        $items = parent::getItems();

        if (!$items || isset($items[0]->count_total)) {
            return $items;
        }

        $db = $this->getDatabase();
        $ids = array_map(static fn ($i) => (int) $i->id, $items);
        $counts = $db->setQuery(
            $db->createQuery()
                ->select('catid, SUM(state >= 0) AS total, SUM(state = 1) AS published')
                ->from($db->quoteName('#__dharmashop_product_categories'))
                ->whereIn($db->quoteName('catid'), $ids)
                ->group('catid')
        )->loadObjectList('catid');

        foreach ($items as $item) {
            $item->count_total = (int) ($counts[$item->id]->total ?? 0);
            $item->count_published = (int) ($counts[$item->id]->published ?? 0);
        }

        return $items;
    }
}
