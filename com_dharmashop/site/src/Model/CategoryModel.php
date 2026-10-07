<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

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
                'p.introtext', 'p.price_min', 'p.price_max', 'p.in_stock', 'p.unit', 'p.params']))
            ->order($sort . ' ' . $direction)->order('p.id ' . $direction);
        $items = $db->setQuery($query, max(0, $start), $limit)->loadObjectList();

        foreach ($items as $item) {
            $item->params = new Registry($item->params);
        }

        return $items;
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
