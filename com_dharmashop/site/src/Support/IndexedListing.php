<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Нефильтрованная выдача «группы и товары» по индексам (C89), без выбора представителя из всех участников каталога.
 *
 * Единица выдачи — самостоятельный товар (строка `product_categories`, а на странице всего каталога — `products`) или группа
 * (строка `group_listing`). Обе ветки читаются по индексу сортировки с `LIMIT смещение + размер страницы` и сливаются; стоимость
 * зависит от номера страницы, а не от размера каталога. Порядок при равных значениях: значение, затем «товар раньше группы», затем
 * номер (в направлении сортировки) — тот же порядок использует запрос с оконной функцией для всех остальных случаев.
 *
 * Применять только без фильтра и поиска, если индекс групп построен и доступ посетителя к категориям проверен (`GroupIndex`).
 */
final class IndexedListing
{
    /** Ключ сортировки ветки товаров категории / всего каталога и ветки групп: [столбец товаров, индекс товаров, столбец групп, индекс групп]. */
    private const SORTS = [
        'order' => ['pc.sort_order', 'idx_l_order', 'gl.sort_order', 'idx_g_order'],
        'price_asc' => ['pc.price_sort', 'idx_l_price', 'gl.price_min', 'idx_g_price'],
        'price_desc' => ['pc.price_sort', 'idx_l_price', 'gl.price_max', 'idx_g_pricemax'],
        'new' => ['pc.created', 'idx_l_new', 'gl.created', 'idx_g_new'],
        'title' => ['pc.title_sort', 'idx_l_title', 'gl.title_sort', 'idx_g_title'],
    ];

    /** То же для страницы всего каталога (`products`). */
    private const ROOT_SORTS = [
        'order' => ['p.id', ''],
        'price_asc' => ['p.price_min', 'idx_state_price'],
        'price_desc' => ['p.price_min', 'idx_state_price'],
        'new' => ['p.created', 'idx_state_created'],
        'title' => ['p.title', 'idx_state_title'],
    ];

    /**
     * @param   int[]  $levels      уровни просмотра посетителя
     * @param   bool   $checkChain  проверять закрытых предков основной категории товара (когда в каталоге есть закрытые категории)
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly array $levels,
        private readonly bool $checkChain,
        private readonly int $category,
    ) {
    }

    /** Выключатель для проверок (сравнение с запросом без индекса). */
    public static bool $enabled = true;

    public static function supports(string $ordering): bool
    {
        return self::$enabled && isset(self::SORTS[$ordering]);
    }

    /** Число единиц выдачи (одиночные товары + группы). */
    public function total(): int
    {
        $db = $this->db;
        $products = $this->category > 0
            ? 'SELECT COUNT(*) FROM #__dharmashop_product_categories pc INNER JOIN #__dharmashop_products p ON p.id = pc.product_id AND (p.group_id IS NULL OR p.group_id = 0) '
                . $this->categoryJoin() . ' WHERE pc.catid = ' . $this->category . ' AND pc.state = 1'
            : 'SELECT COUNT(*) FROM #__dharmashop_products p ' . $this->categoryJoin() . ' WHERE p.state = 1 AND (p.group_id IS NULL OR p.group_id = 0)';

        return (int) $db->setQuery($products)->loadResult()
            + (int) $db->setQuery('SELECT COUNT(*) FROM #__dharmashop_group_listing WHERE catid = ' . $this->category)->loadResult();
    }

    /**
     * Единицы страницы в порядке выдачи.
     *
     * @return  array<int, array{kind: int, id: int}>  kind 0 — товар (id товара), 1 — группа (id группы)
     */
    public function page(string $ordering, int $start, int $limit): array
    {
        $descending = in_array($ordering, ['price_desc', 'new'], true);
        $direction = $descending ? 'DESC' : 'ASC';
        $n = max(1, $start + $limit);
        [$column, $index, $groupColumn, $groupIndex] = self::SORTS[$ordering] + [null, null, null, null];

        if ($this->category > 0) {
            $products = "SELECT pc.product_id AS id, $column AS k FROM #__dharmashop_product_categories pc FORCE INDEX ($index) "
                . 'STRAIGHT_JOIN #__dharmashop_products p ON p.id = pc.product_id AND (p.group_id IS NULL OR p.group_id = 0) '
                . $this->categoryJoin() . ' WHERE pc.catid = ' . $this->category . " AND pc.state = 1 ORDER BY $column $direction, pc.product_id $direction LIMIT $n";
        } else {
            [$rootColumn, $rootIndex] = self::ROOT_SORTS[$ordering];
            $force = $rootIndex !== '' ? " FORCE INDEX ($rootIndex)" : '';
            $products = "SELECT p.id AS id, $rootColumn AS k FROM #__dharmashop_products p$force "
                . $this->categoryJoin() . " WHERE p.state = 1 AND (p.group_id IS NULL OR p.group_id = 0) ORDER BY $rootColumn $direction, p.id $direction LIMIT $n";
        }

        $groups = "SELECT gl.group_id AS id, $groupColumn AS k FROM #__dharmashop_group_listing gl FORCE INDEX ($groupIndex) "
            . 'WHERE gl.catid = ' . $this->category . " ORDER BY $groupColumn $direction, gl.group_id $direction LIMIT $n";
        $sql = "(SELECT 0 AS kind, a.id, a.k FROM ($products) a) UNION ALL (SELECT 1 AS kind, b.id, b.k FROM ($groups) b) ORDER BY k $direction, kind ASC, id $direction";
        $rows = $this->db->setQuery($sql, max(0, $start), $limit)->loadObjectList();

        return array_map(static fn (object $row): array => ['kind' => (int) $row->kind, 'id' => (int) $row->id], $rows);
    }

    /**
     * Базовое состояние фильтра области без условий (числа значений, границы, цена, бренды, бейджи, подкатегории) в единицах
     * выдачи: группы читаются из сводных таблиц `group_keys`/`group_bounds` (по строке группы, не по участникам), самостоятельные
     * товары — запросами по индексам. Результат совпадает с подсчётом по кандидатам (`ScopedFacets::aggregate()`).
     *
     * @return  array{options: array<int, array<int, int>>, ranges: array<int, array{min: float, max: float}>, price: array{min: float, max: float}|null,
     *                directories: array{brand: array<int, int>, badge: array<int, int>, cat: array<int, int>}}
     */
    public function facets(\Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinition $definition): array
    {
        $db = $this->db;
        $x = $this->category;
        $optionFields = [];
        $numberFields = [];

        foreach ($definition->fields as $field) {
            if ($field->kind === \Dharma\Component\DharmaShop\Administrator\Filter\FilterField::OPTION) {
                $optionFields[] = (int) $field->id;
            } else {
                $numberFields[] = (int) $field->id;
            }
        }

        // Самостоятельные товары области (каждый — своя единица).
        $scope = $x > 0
            ? 'FROM #__dharmashop_product_categories pc INNER JOIN #__dharmashop_products p ON p.id = pc.product_id AND (p.group_id IS NULL OR p.group_id = 0) ' . $this->categoryJoin() . ' WHERE pc.catid = ' . $x . ' AND pc.state = 1'
            : 'FROM #__dharmashop_products p ' . $this->categoryJoin() . ' WHERE p.state = 1 AND (p.group_id IS NULL OR p.group_id = 0)';
        $options = [];
        $ranges = [];

        if ($optionFields) {
            $in = implode(',', $optionFields);
            $join = ' INNER JOIN #__dharmashop_field_values fv ON fv.product_id = p.id AND fv.field_id IN (' . $in . ') AND fv.option_id IS NOT NULL';

            foreach ($db->setQuery('SELECT fv.field_id, fv.option_id, COUNT(DISTINCT p.id) AS n ' . $this->withJoin($scope, $join) . ' GROUP BY fv.field_id, fv.option_id')->loadObjectList() as $row) {
                $options[(int) $row->field_id][(int) $row->option_id] = (int) $row->n;
            }

            foreach ($db->setQuery(
                'SELECT gk.ref AS field_id, gk.opt AS option_id, COUNT(*) AS n FROM #__dharmashop_group_listing gl '
                . 'INNER JOIN #__dharmashop_group_keys gk ON gk.group_id = gl.group_id AND gk.kind = 1 AND gk.ref IN (' . $in . ') '
                . 'WHERE gl.catid = ' . $x . ' GROUP BY gk.ref, gk.opt'
            )->loadObjectList() as $row) {
                $options[(int) $row->field_id][(int) $row->option_id] = ($options[(int) $row->field_id][(int) $row->option_id] ?? 0) + (int) $row->n;
            }
        }

        if ($numberFields) {
            $in = implode(',', $numberFields);
            $join = ' INNER JOIN #__dharmashop_field_values fv ON fv.product_id = p.id AND fv.field_id IN (' . $in . ') AND fv.num IS NOT NULL';
            $found = [];

            foreach ($db->setQuery('SELECT fv.field_id, MIN(fv.num) AS mn, MAX(COALESCE(fv.num_end, fv.num)) AS mx ' . $this->withJoin($scope, $join) . ' GROUP BY fv.field_id')->loadObjectList() as $row) {
                $found[(int) $row->field_id][] = [(float) $row->mn, (float) $row->mx];
            }

            foreach ($db->setQuery(
                'SELECT gb.ref AS field_id, MIN(gb.num_min) AS mn, MAX(gb.num_max) AS mx FROM #__dharmashop_group_listing gl '
                . 'INNER JOIN #__dharmashop_group_bounds gb ON gb.group_id = gl.group_id AND gb.ref IN (' . $in . ') WHERE gl.catid = ' . $x . ' GROUP BY gb.ref'
            )->loadObjectList() as $row) {
                if ($row->mn !== null) {
                    $found[(int) $row->field_id][] = [(float) $row->mn, (float) $row->mx];
                }
            }

            foreach ($found as $fieldId => $pairs) {
                $ranges[$fieldId] = ['min' => min(array_column($pairs, 0)), 'max' => max(array_column($pairs, 1))];
            }
        }

        // Бренды, бейджи и подкатегории: число единиц, у которых есть соответствующая привязка.
        $directories = ['brand' => [], 'badge' => [], 'cat' => []];
        $ids = array_values(array_unique(array_map('intval', array_merge(array_keys($definition->brands), array_keys($definition->badges), array_keys($definition->subcategories)))));

        if ($ids) {
            $in = implode(',', $ids);
            $subcategories = $definition->subcategories ? implode(',', array_map('intval', array_keys($definition->subcategories))) : '0';
            $join = ' INNER JOIN #__dharmashop_product_categories fd ON fd.product_id = p.id AND fd.state = 1 AND fd.catid IN (' . $in . ') AND (fd.direct = 1 OR fd.catid IN (' . $subcategories . '))';
            $count = [];

            foreach ($db->setQuery('SELECT fd.catid, COUNT(DISTINCT p.id) AS n ' . $this->withJoin($scope, $join) . ' GROUP BY fd.catid')->loadObjectList() as $row) {
                $count[(int) $row->catid] = (int) $row->n;
            }

            foreach ($db->setQuery(
                'SELECT gk.ref AS catid, COUNT(*) AS n FROM #__dharmashop_group_listing gl '
                . 'INNER JOIN #__dharmashop_group_keys gk ON gk.group_id = gl.group_id AND gk.kind IN (2, 3, 4) AND gk.ref IN (' . $in . ') '
                . 'WHERE gl.catid = ' . $x . ' GROUP BY gk.ref'
            )->loadObjectList() as $row) {
                $count[(int) $row->catid] = ($count[(int) $row->catid] ?? 0) + (int) $row->n;
            }

            foreach ($count as $id => $n) {
                if (isset($definition->brands[$id])) { $directories['brand'][$id] = $n; }
                if (isset($definition->badges[$id])) { $directories['badge'][$id] = $n; }
                if (isset($definition->subcategories[$id])) { $directories['cat'][$id] = $n; }
            }
        }

        // Цена: товары с положительной ценой.
        $price = null;
        $row = $db->setQuery('SELECT MIN(p.price_min) AS mn, MAX(p.price_max) AS mx ' . $scope . ' AND p.price_min > 0')->loadObject();
        $bounds = [];

        if ($row && $row->mn !== null) {
            $bounds[] = [(float) $row->mn, (float) $row->mx];
        }

        $row = $db->setQuery(
            'SELECT MIN(gb.num_min) AS mn, MAX(gb.num_max) AS mx FROM #__dharmashop_group_listing gl '
            . 'INNER JOIN #__dharmashop_group_bounds gb ON gb.group_id = gl.group_id AND gb.ref = 0 WHERE gl.catid = ' . $x
        )->loadObject();

        if ($row && $row->mn !== null) {
            $bounds[] = [(float) $row->mn, (float) $row->mx];
        }

        if ($bounds) {
            $price = ['min' => min(array_column($bounds, 0)), 'max' => max(array_column($bounds, 1))];
        }

        return ['options' => $options, 'ranges' => $ranges, 'price' => $price, 'directories' => $directories];
    }

    /** Вставляет соединение перед WHERE выражения области. */
    private function withJoin(string $scope, string $join): string
    {
        $position = strrpos($scope, ' WHERE ');

        return substr($scope, 0, $position) . $join . substr($scope, $position);
    }

    /** Основная категория товара: опубликована, доступна посетителю и (при закрытых категориях) без закрытых предков. */
    private function categoryJoin(): string
    {
        $levels = implode(',', array_map('intval', $this->levels) ?: [0]);
        $catalog = $this->db->quote('com_dharmashop');
        $join = "INNER JOIN #__categories c ON c.id = p.catid AND c.extension = $catalog AND c.published = 1 AND c.access IN ($levels)";

        if ($this->checkChain) {
            $join .= " AND NOT EXISTS (SELECT 1 FROM #__categories blocked WHERE blocked.extension = $catalog AND blocked.lft <= c.lft AND blocked.rgt >= c.rgt AND (blocked.published <> 1 OR blocked.access NOT IN ($levels)))";
        }

        return $join;
    }
}
