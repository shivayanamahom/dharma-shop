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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Список товаров в админке: фильтры по состоянию, категории (с подкатегориями), рекомендуемым и наличию; поиск.
 */
class ProductsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id', 'title', 'a.title', 'state', 'a.state', 'featured', 'a.featured',
                'created', 'a.created', 'price_min', 'a.price_min', 'category_title', 'category_id', 'published',
                'in_stock', 'a.in_stock',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        foreach (['filter.search', 'filter.published', 'filter.category_id', 'filter.featured', 'filter.in_stock'] as $key) {
            $id .= ':' . $this->getState($key);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery();

        $query->select($db->quoteName([
            'a.id', 'a.title', 'a.alias', 'a.catid', 'a.state', 'a.featured', 'a.price_min', 'a.price_max',
            'a.in_stock', 'a.variants_count', 'a.created', 'a.created_by', 'a.checked_out', 'a.checked_out_time',
        ]))
            ->select($db->quoteName('c.title', 'category_title'))
            ->select($db->quoteName('v.sku', 'sku'))
            ->select($db->quoteName('uc.name', 'editor'))
            ->from($db->quoteName('#__dharmashop_products', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->join('LEFT', $db->quoteName('#__dharmashop_variants', 'v') . ' ON v.product_id = a.id AND v.is_default = 1')
            ->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON uc.id = a.checked_out');

        $published = $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.state') . ' = :published')->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '' || $published === null) {
            $query->whereIn($db->quoteName('a.state'), [0, 1]);
        }

        $categoryId = (int) $this->getState('filter.category_id');

        if ($categoryId) {
            // Через витринный индекс: включает подкатегории и дополнительные категории.
            $query->where('a.id IN (SELECT pc.product_id FROM ' . $db->quoteName('#__dharmashop_product_categories') . ' AS pc WHERE pc.catid = :catid)')
                ->bind(':catid', $categoryId, ParameterType::INTEGER);
        }

        $featured = $this->getState('filter.featured');

        if (is_numeric($featured)) {
            $featured = (int) $featured;
            $query->where($db->quoteName('a.featured') . ' = :featured')->bind(':featured', $featured, ParameterType::INTEGER);
        }

        $inStock = $this->getState('filter.in_stock');

        if (is_numeric($inStock)) {
            $inStock = (int) $inStock;
            $query->where($db->quoteName('a.in_stock') . ' = :instock')->bind(':instock', $inStock, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } elseif (stripos($search, 'sku:') === 0) {
                $sku = trim(substr($search, 4)) . '%';
                $query->where('a.id IN (SELECT sv.product_id FROM ' . $db->quoteName('#__dharmashop_variants') . ' AS sv WHERE sv.sku LIKE :sku)')->bind(':sku', $sku);
            } else {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.alias') . ' LIKE :s2)')
                    ->bind(':s1', $like)->bind(':s2', $like);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = ['a.id', 'a.title', 'a.state', 'a.featured', 'a.created', 'a.price_min', 'a.in_stock', 'category_title'];
        $orderCol = in_array($orderCol, $allowed, true) ? $orderCol : 'a.id';
        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }
}
