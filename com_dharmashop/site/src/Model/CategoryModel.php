<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Товары категории: проверка категории и её детей общая со списком категорий. */
class CategoryModel extends CategoriesModel
{
    /** Колонки сортировки выбираются только из этого списка. */
    public const SORTS = ['order', 'price_asc', 'price_desc', 'new', 'title'];

    /** @return array{items: array, next: int} Порция по ключу, без OFFSET и дублей товара. */
    public function getProducts(string $ordering, int $limit, int $after = 0): array
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
        $descending = in_array($ordering, ['price_desc', 'new'], true);
        $direction = $descending ? 'DESC' : 'ASC';
        $comparison = $descending ? '<' : '>';
        $sort = $db->quoteName($column);
        $query = $this->productsQuery();

        if ($after > 0) {
            $anchorQuery = clone $query;
            $anchorQuery->select($sort . ' AS ' . $db->quoteName('cursor_value'))
                ->where('p.id = :anchor')->bind(':anchor', $after, ParameterType::INTEGER);
            $anchor = $db->setQuery($anchorQuery)->loadObject();
            if (!$anchor) {
                throw new \RuntimeException(Text::_('COM_DHARMASHOP_CATEGORY_NOT_FOUND'), 404);
            }
            $query->bind(':cursor_id', $after, ParameterType::INTEGER);
            if ($anchor->cursor_value === null) {
                $condition = '(' . $sort . ' IS NULL AND p.id ' . $comparison . ' :cursor_id)';
                $query->where($descending ? $condition : '(' . $sort . ' IS NOT NULL OR ' . $condition . ')');
            } else {
                $value = (string) $anchor->cursor_value;
                $query->where('(' . $sort . ' ' . $comparison . ' :cursor_value OR (' . $sort . ' = :cursor_equal AND p.id '
                    . $comparison . ' :cursor_id)' . ($descending ? ' OR ' . $sort . ' IS NULL' : '') . ')')
                    ->bind(':cursor_value', $value)->bind(':cursor_equal', $value);
            }
        }

        $query->select($db->quoteName(['p.id', 'p.catid', 'p.title', 'p.alias', 'p.cover', 'p.cover_alt',
            'p.introtext', 'p.price_min', 'p.price_max', 'p.in_stock', 'p.unit', 'p.params']))
            ->order($sort . ' ' . $direction)->order('p.id ' . $direction);
        $items = $db->setQuery($query, 0, $limit + 1)->loadObjectList();
        $more = count($items) > $limit;
        if ($more) {
            array_pop($items);
        }
        foreach ($items as $item) {
            $item->params = new Registry($item->params);
        }

        return ['items' => $items, 'next' => $more && $items ? (int) end($items)->id : 0];
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
