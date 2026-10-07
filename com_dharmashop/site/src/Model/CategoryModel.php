<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\SaleCountdown;
use Dharma\Component\DharmaShop\Site\Support\ProductFields;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Товары категории: проверка категории и её детей общая со списком категорий. */
class CategoryModel extends CategoriesModel
{
    /** Колонки сортировки выбираются только из этого списка. */
    public const SORTS = ['order', 'price_asc', 'price_desc', 'new', 'title'];

    /** Число видимых товаров списка (для постраничной навигации). */
    public function getTotal(): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery($this->productsQuery()->select('COUNT(*)'))->loadResult();
    }

    /**
     * Страница товаров: со смещением $start. Сортировка стабильна при равных значениях (второй ключ — id).
     * Смещение идёт по узкому индексу категории; глубокие страницы дороже первых (см. DECISIONS C44).
     *
     * @return array Товары страницы.
     */
    public function getProducts(string $ordering, int $limit, int $start = 0): array
    {
        $ordering = in_array($ordering, self::SORTS, true) ? $ordering : 'order';
        $db = $this->getDatabase();
        $category = (int) $this->getParent()->id;
        $column = $category ? match ($ordering) {
            'price_asc', 'price_desc' => 'pc.price_sort',
            'new' => 'pc.created',
            'title' => 'pc.title_sort',
            default => 'pc.sort_order',
        } : match ($ordering) {
            'price_asc', 'price_desc' => 'p.price_min',
            'new' => 'p.created',
            'title' => 'p.title',
            default => 'p.id',
        };
        $direction = in_array($ordering, ['price_desc', 'new'], true) ? 'DESC' : 'ASC';
        $sort = $db->quoteName($column);
        $query = $this->productsQuery()
            ->select($db->quoteName(['p.id', 'p.catid', 'p.title', 'p.alias', 'p.cover', 'p.cover_alt',
                'p.introtext', 'p.price_min', 'p.price_max', 'p.in_stock', 'p.unit', 'p.qty_min', 'p.qty_step', 'p.params']))
            ->select($db->quoteName('c.title', 'category_title'))
            ->order($sort . ' ' . $direction)->order('p.id ' . $direction);
        $items = $db->setQuery($query, max(0, $start), $limit)->loadObjectList();

        foreach ($items as $item) {
            $item->params = new Registry($item->params);
        }

        return $items;
    }

    /**
     * Данные карточек страницы одним пакетом (без запросов на каждую карточку): обычная цена и размер скидки варианта по умолчанию
     * и бейджи товара (только опубликованные, доступные посетителю). Поля добавляются в объекты товаров: base_price, discount_amount, discount_percent, badges.
     *
     * @param   object[]  $items
     */
    public function attachCardData(array $items, int $imagesLimit = 1): void
    {
        if (!$items) {
            return;
        }

        $db = $this->getDatabase();
        $ids = array_map(static fn (object $item): int => (int) $item->id, $items);
        ProductFields::attach($db, $items, 'card');
        $list = implode(',', $ids);
        $prices = [];

        foreach ($db->setQuery(
            'SELECT v.product_id, vp.price, vp.price_final, vp.discount_start, vp.discount_end FROM ' . $db->quoteName('#__dharmashop_variants', 'v')
            . ' JOIN ' . $db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id'
            . ' WHERE v.product_id IN (' . $list . ') AND v.is_default = 1 AND vp.price_list_id = 1'
        )->loadObjectList() as $row) {
            $prices[(int) $row->product_id] = $row;
        }

        // Галерея товара для карусели в карточке: только если выводится больше одного фото (иначе хватает обложки). 0 — все (не больше 20).
        $images = [];

        if ($imagesLimit !== 1) {
            $limit = $imagesLimit > 0 ? $imagesLimit : 20;

            foreach ($db->setQuery(
                'SELECT product_id, path, alt FROM ' . $db->quoteName('#__dharmashop_field_media')
                . ' WHERE product_id IN (' . $list . ') AND field_id = 0 AND variant_id IS NULL ORDER BY product_id, ordering'
            )->loadObjectList() as $row) {
                if (count($images[(int) $row->product_id] ?? []) < $limit) {
                    $images[(int) $row->product_id][] = (object) ['path' => (string) $row->path, 'alt' => (string) $row->alt];
                }
            }
        }

        $levels = implode(',', array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0]);
        $badges = [];

        foreach ($db->setQuery(
            'SELECT pc.product_id, c.id, c.title, c.params FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $db->quoteName('#__categories', 'c') . ' ON c.id = pc.catid'
            . ' WHERE pc.product_id IN (' . $list . ') AND pc.direct = 1 AND c.extension = ' . $db->quote('com_dharmashop.badge')
            . ' AND c.published = 1 AND c.access IN (' . $levels . ') ORDER BY c.lft'
        )->loadObjectList() as $row) {
            $params = new Registry($row->params);

            // Настройка бейджа «Показывать в списках»: выключенный бейдж на карточках не выводится.
            if ((int) $params->get('badge_show_list', 1) !== 1) {
                continue;
            }

            $color = (string) $params->get('badge_color', 'primary');
            $badges[(int) $row->product_id][] = (object) [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'color' => in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $color : 'primary',
            ];
        }

        foreach ($items as $item) {
            $id = (int) $item->id;
            $item->badges = $badges[$id] ?? [];
            $item->images = $images[$id] ?? [];
            $item->base_price = null;
            $item->discount_amount = null;
            $item->discount_percent = 0;
            $item->sale_end_timestamp = null;
            $row = $prices[$id] ?? null;

            if ($row && $row->price !== null && $row->price_final !== null && (float) $row->price > 0 && (float) $row->price_final < (float) $row->price) {
                $item->base_price = (string) $row->price;
                $item->discount_amount = number_format((float) $row->price - (float) $row->price_final, 2, '.', '');
                $item->discount_percent = (1 - (float) $row->price_final / (float) $row->price) * 100;
                $item->sale_end_timestamp = SaleCountdown::endTimestamp($row->discount_start, $row->discount_end, true);
            }
        }
    }

    /** Итоги всей категории (по индексу, с потолком); цены нужны только если включены настройками. Корень цен не имеет. */
    public function getProductStats(bool $prices): object
    {
        $category = (int) $this->getParent()->id;
        $range = $prices ? $this->getPriceRange($category) : ['min' => null, 'max' => null];

        return (object) ['product_count' => $this->getCountLabel($category), 'price_min' => $range['min'], 'price_max' => $range['max']];
    }

    private function productsQuery(): QueryInterface
    {
        $category = (int) $this->getParent()->id;
        $db = $this->getDatabase();
        $levels = implode(',', array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0]);
        $query = $db->createQuery()->from($db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where('p.state = 1')->where('c.extension = ' . $db->quote('com_dharmashop'))
            ->where('c.published = 1')->where('c.access IN (' . $levels . ')');
        if ($category > 0) {
            $query->join('INNER', $db->quoteName('#__dharmashop_product_categories', 'pc') . ' ON pc.product_id = p.id')
                ->where('pc.catid = :category')->where('pc.state = 1')->bind(':category', $category, ParameterType::INTEGER);
        }
        // Закрытый или снятый предок основной категории также скрывает товар.
        $blocked = $db->createQuery()->select('blocked.id')->from($db->quoteName('#__categories', 'blocked'))
            ->where('blocked.extension = ' . $db->quote('com_dharmashop'))
            ->where('blocked.lft <= c.lft')->where('blocked.rgt >= c.rgt')
            ->where('(blocked.published != 1 OR blocked.access NOT IN (' . $levels . '))');
        $query->where('NOT EXISTS (' . $blocked . ')');

        return $query;
    }
}
