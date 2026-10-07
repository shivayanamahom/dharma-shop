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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Список товаров в админке. Фильтры: состояние, основная категория, дополнительные категории, бренд, бейдж,
 * «все категории» (с подкатегориями) и наличие. Поиск: по названию, алиасу и артикулу; префиксы id:, title:, alias:, sku:
 * дают точное совпадение, фраза в кавычках — точное совпадение с названием, алиасом или артикулом.
 */
class ProductsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id', 'title', 'a.title', 'state', 'a.state', 'created', 'a.created', 'price_min', 'a.price_min',
                'category_title', 'sku', 'in_stock', 'a.in_stock',
                'published', 'catid', 'extra_catid', 'brand_id', 'badge_id', 'category_id',
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
        foreach (['search', 'published', 'catid', 'extra_catid', 'brand_id', 'badge_id', 'category_id', 'in_stock'] as $key) {
            $id .= ':' . $this->getState('filter.' . $key);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery();
        $pc = $db->quoteName('#__dharmashop_product_categories');

        $query->select($db->quoteName([
            'a.id', 'a.title', 'a.alias', 'a.catid', 'a.state', 'a.cover', 'a.price_min', 'a.price_max',
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

        // Основная категория: точное совпадение.
        if ($catid = (int) $this->getState('filter.catid')) {
            $query->where($db->quoteName('a.catid') . ' = :catid')->bind(':catid', $catid, ParameterType::INTEGER);
        }

        // Дополнительные категории: прямая привязка, не основная.
        if ($extra = (int) $this->getState('filter.extra_catid')) {
            $query->where('a.id IN (SELECT pc.product_id FROM ' . $pc . ' AS pc WHERE pc.catid = :extra AND pc.direct = 1 AND pc.is_main = 0)')
                ->bind(':extra', $extra, ParameterType::INTEGER);
        }

        // Бренд: через витринный индекс — у вложенных брендов находятся товары дочерних. Бейдж: плоский, прямая привязка.
        if ($brand = (int) $this->getState('filter.brand_id')) {
            $query->where('a.id IN (SELECT pc.product_id FROM ' . $pc . ' AS pc WHERE pc.catid = :brand)')->bind(':brand', $brand, ParameterType::INTEGER);
        }

        if ($badge = (int) $this->getState('filter.badge_id')) {
            $query->where('a.id IN (SELECT pc.product_id FROM ' . $pc . ' AS pc WHERE pc.catid = :badge AND pc.direct = 1)')->bind(':badge', $badge, ParameterType::INTEGER);
        }

        // Все категории: через витринный индекс, включая подкатегории и дополнительные.
        if ($all = (int) $this->getState('filter.category_id')) {
            $query->where('a.id IN (SELECT pc.product_id FROM ' . $pc . ' AS pc WHERE pc.catid = :allcat)')->bind(':allcat', $all, ParameterType::INTEGER);
        }

        $inStock = $this->getState('filter.in_stock');

        if (is_numeric($inStock)) {
            $inStock = (int) $inStock;
            $query->where($db->quoteName('a.in_stock') . ' = :instock')->bind(':instock', $inStock, ParameterType::INTEGER);
        }

        $this->applySearch($query, trim((string) $this->getState('filter.search')));

        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = ['a.id', 'a.title', 'a.state', 'a.created', 'a.price_min', 'a.in_stock', 'category_title', 'sku'];
        $orderCol = in_array($orderCol, $allowed, true) ? $orderCol : 'a.id';
        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }

    private function applySearch(QueryInterface $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $db = $this->getDatabase();
        $variants = $db->quoteName('#__dharmashop_variants');

        // Префиксы: точное совпадение.
        if (preg_match('/^(id|title|alias|sku):\s*(.*)$/iu', $search, $m)) {
            $value = trim($m[2]);

            switch (strtolower($m[1])) {
                case 'id':
                    $id = (int) $value;
                    $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
                    break;
                case 'title':
                    $query->where($db->quoteName('a.title') . ' = :ptitle')->bind(':ptitle', $value);
                    break;
                case 'alias':
                    $query->where($db->quoteName('a.alias') . ' = :palias')->bind(':palias', $value);
                    break;
                default:
                    $query->where('a.id IN (SELECT sv.product_id FROM ' . $variants . ' AS sv WHERE sv.sku = :psku)')->bind(':psku', $value);
            }

            return;
        }

        // Фраза в кавычках: точное совпадение с названием, алиасом или артикулом.
        if (preg_match('/^["«](.+)["»]$/u', $search, $m)) {
            $phrase = $m[1];
            $query->where('(' . $db->quoteName('a.title') . ' = :q1 OR ' . $db->quoteName('a.alias') . ' = :q2 OR a.id IN (SELECT sv.product_id FROM ' . $variants . ' AS sv WHERE sv.sku = :q3))')
                ->bind(':q1', $phrase)->bind(':q2', $phrase)->bind(':q3', $phrase);

            return;
        }

        // Обычный поиск: вхождение в название, алиас, артикул любого варианта или «текст для поиска».
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
        $query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.alias') . ' LIKE :s2 OR a.id IN (SELECT sv.product_id FROM ' . $variants . ' AS sv WHERE sv.sku LIKE :s3) OR ' . $db->quoteName('a.search_text') . ' LIKE :s4)')
            ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like);
    }

    /**
     * К строкам страницы добавляются привязки: основная и дополнительные категории, бренд, бейджи
     * (одним запросом по id товаров страницы, не по каждому товару).
     *
     * @return  object[]
     */
    public function getItems()
    {
        $items = parent::getItems();

        if (!$items) {
            return $items;
        }

        $db = $this->getDatabase();
        $ids = array_map(static fn (object $item): int => (int) $item->id, $items);
        $rows = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['pc.product_id', 'pc.is_main', 'c.id', 'c.title', 'c.extension', 'c.params']))
                ->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
                ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = pc.catid')
                ->whereIn($db->quoteName('pc.product_id'), $ids)
                ->where($db->quoteName('pc.direct') . ' = 1')
                ->order($db->quoteName('c.lft'))
        )->loadObjectList();

        foreach ($items as $item) {
            $item->main = null;
            $item->extra = [];
            $item->brand = null;
            $item->badges = [];
        }

        $byId = [];

        foreach ($items as $item) {
            $byId[(int) $item->id] = $item;
        }

        foreach ($rows as $row) {
            $item = $byId[(int) $row->product_id] ?? null;

            if ($item === null) {
                continue;
            }

            $entry = (object) ['id' => (int) $row->id, 'title' => (string) $row->title, 'extension' => (string) $row->extension];

            if ($row->extension === CategoryTypes::BRAND) {
                $item->brand ??= $entry;
            } elseif ($row->extension === CategoryTypes::BADGE) {
                $params = json_decode((string) $row->params, true) ?: [];
                $entry->color = preg_match('/^[a-z]{3,12}$/', (string) ($params['badge_color'] ?? '')) ? $params['badge_color'] : 'primary';
                $item->badges[] = $entry;
            } elseif ((int) $row->is_main === 1) {
                $item->main = $entry;
            } else {
                $item->extra[] = $entry;
            }
        }

        return $items;
    }
}
