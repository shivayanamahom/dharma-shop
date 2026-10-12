<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Индекс выдачи групп (C89). Нефильтрованный список «групп и товаров» не должен выбирать представителя из всех участников каталога:
 * ему нужны готовые строки групп по категориям со столбцами сортировки (как `product_categories` у товаров).
 *
 * - `group_listing (catid, group_id, …)`: группа показывается в категории, если у неё есть привязка (основная, дополнительная,
 *   бренд, бейдж) к этой категории или к её потомку; `catid = 0` — страница всего каталога. Столбцы сортировки — агрегаты по
 *   опубликованным участникам: порядок, минимальная и максимальная цена, дата, название. Строки есть только у опубликованных групп с
 *   хотя бы одним доступным участником в опубликованных категориях.
 * - `group_keys`: объединённые по участникам значения полей, бренды, бейджи и категории (счётчики фильтра без обхода участников).
 * - `group_bounds`: границы чисел и цены по участникам.
 *
 * Доступ посетителя (уровни просмотра) в индекс не входит: он проверяется отдельно (`accessible()`), при ограниченных категориях
 * витрина считает по участникам. Источник правды — таблицы групп, товаров и категорий; `rebuild()` вызывается из `GroupService::rebuild()`.
 */
final class GroupIndex
{
    private const CHUNK = 1000;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Пересобирает строки перечисленных групп (null — всех). Безопасно вызывать внутри открытой транзакции.
     *
     * @param   int[]|null  $groupIds
     */
    public function rebuild(?array $groupIds = null): void
    {
        if ($groupIds === null) {
            $groupIds = array_map('intval', $this->db->setQuery('SELECT id FROM #__dharmashop_product_groups ORDER BY id')->loadColumn());
            // Строки несуществующих групп.
            $this->db->setQuery('DELETE gl FROM #__dharmashop_group_listing gl LEFT JOIN #__dharmashop_product_groups g ON g.id = gl.group_id WHERE g.id IS NULL')->execute();
            $this->db->setQuery('DELETE gk FROM #__dharmashop_group_keys gk LEFT JOIN #__dharmashop_product_groups g ON g.id = gk.group_id WHERE g.id IS NULL')->execute();
            $this->db->setQuery('DELETE gb FROM #__dharmashop_group_bounds gb LEFT JOIN #__dharmashop_product_groups g ON g.id = gb.group_id WHERE g.id IS NULL')->execute();
        }

        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

        foreach (array_chunk($groupIds, self::CHUNK) as $chunk) {
            $this->rebuildChunk(implode(',', $chunk));
        }

        FilterCache::forget();
    }

    /** Группы перечисленных товаров. @param int[] $productIds */
    public function rebuildForProducts(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$ids) {
            return;
        }

        $groups = $this->db->setQuery('SELECT DISTINCT group_id FROM #__dharmashop_products WHERE id IN (' . implode(',', $ids) . ') AND group_id > 0')->loadColumn();
        $this->rebuild(array_map('intval', $groups));
    }

    /** Группы, привязанные к категориям поддерева (после переноса, публикации или смены доступа категории). */
    public function rebuildForCategory(int $categoryId): void
    {
        // Привязки групп внутри поддерева и участники, чья основная категория в поддереве.
        $root = 'INNER JOIN #__categories root ON root.id = ' . (int) $categoryId . ' AND c.extension = root.extension AND c.lft >= root.lft AND c.rgt <= root.rgt';
        $groups = $this->db->setQuery(
            "SELECT gc.group_id FROM #__dharmashop_group_categories gc INNER JOIN #__categories c ON c.id = gc.catid $root "
            . "UNION SELECT p.group_id FROM #__dharmashop_products p INNER JOIN #__categories c ON c.id = p.catid $root WHERE p.group_id > 0"
        )->loadColumn();
        $this->rebuild(array_map('intval', $groups));
    }

    /** Удаляет строки групп (перед удалением группы). @param int[] $groupIds */
    public function delete(array $groupIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

        if (!$ids) {
            return;
        }

        $in = implode(',', $ids);

        foreach (['listing', 'keys', 'bounds'] as $table) {
            $this->db->setQuery('DELETE FROM #__dharmashop_group_' . $table . ' WHERE group_id IN (' . $in . ')')->execute();
        }

        FilterCache::forget();
    }

    /**
     * Индекс построен: есть строки, либо опубликованных групп нет. Пустой индекс при существующих группах (таблицы только создали)
     * нельзя считать «групп нет».
     */
    public function ready(): bool
    {
        return (bool) FilterCache::remember($this->db->getPrefix() . ':group-index-ready', function (): bool {
            if ($this->db->setQuery('SELECT 1 FROM #__dharmashop_group_listing', 0, 1)->loadResult()) {
                return true;
            }

            return !$this->db->setQuery('SELECT 1 FROM #__dharmashop_product_groups WHERE state = 1', 0, 1)->loadResult();
        });
    }

    /**
     * Все опубликованные категории витрины, бренды и бейджи доступны посетителю с этими уровнями просмотра. Только тогда строки
     * индекса (в которых учтена публикация, но не доступ) совпадают с проверкой по участникам. Результат кэшируется.
     *
     * @param   int[]  $levels
     */
    public function accessible(array $levels): bool
    {
        $in = implode(',', array_map('intval', $levels) ?: [0]);

        return (bool) FilterCache::remember($this->db->getPrefix() . ':group-index-access:' . $in, function () use ($in): bool {
            return !$this->db->setQuery(
                "SELECT id FROM #__categories WHERE extension IN ('com_dharmashop', 'com_dharmashop.brand', 'com_dharmashop.badge') AND published = 1 AND access NOT IN ($in)",
                0,
                1
            )->loadResult();
        });
    }

    /**
     * Сверка индекса с пересчётом по участникам. Возвращает число групп, у которых строки расходятся (0 — согласовано).
     *
     * @param   int  $limit  сколько групп проверить (первые по номеру)
     */
    public function verify(int $limit = 2000): int
    {
        $ids = array_map('intval', $this->db->setQuery('SELECT id FROM #__dharmashop_product_groups ORDER BY id', 0, max(1, $limit))->loadColumn());
        $bad = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $in = implode(',', $chunk);
            $expected = $this->snapshotOf($this->listingSelect($in), ['catid', 'group_id'], ['sort_order', 'price_min', 'price_max', 'created', 'title_sort', 'in_stock']);
            $stored = $this->snapshotOf(
                'SELECT catid, group_id, sort_order, price_min, price_max, created, title_sort, in_stock FROM #__dharmashop_group_listing WHERE group_id IN (' . $in . ')',
                ['catid', 'group_id'],
                ['sort_order', 'price_min', 'price_max', 'created', 'title_sort', 'in_stock']
            );
            $groups = [];
            $keys = [
                ['group_id', ['group_id', 'kind', 'ref', 'opt'], [], $this->keysSelect($in), 'SELECT group_id, kind, ref, opt FROM #__dharmashop_group_keys WHERE group_id IN (' . $in . ')'],
                ['group_id', ['group_id', 'ref'], ['num_min', 'num_max'], $this->boundsSelect($in), 'SELECT group_id, ref, num_min, num_max FROM #__dharmashop_group_bounds WHERE group_id IN (' . $in . ')'],
            ];

            foreach ($keys as [$groupColumn, $keyColumns, $valueColumns, $expectedSql, $storedSql]) {
                $e = $this->snapshotOf($expectedSql, $keyColumns, $valueColumns);
                $st = $this->snapshotOf($storedSql, $keyColumns, $valueColumns);

                foreach (array_keys($e + $st) as $key) {
                    if (($e[$key] ?? null) !== ($st[$key] ?? null)) {
                        $groups[(int) explode(':', (string) $key)[0]] = true;
                    }
                }
            }

            foreach (array_keys($expected + $stored) as $key) {
                if (($expected[$key] ?? null) !== ($stored[$key] ?? null)) {
                    $groups[(int) explode(':', (string) $key)[1]] = true;
                }
            }

            $bad += count($groups);
        }

        return $bad;
    }

    private function rebuildChunk(string $in): void
    {
        $db = $this->db;
        $db->transactionStart(true);

        try {
            foreach (['listing', 'keys', 'bounds'] as $table) {
                $db->setQuery('DELETE FROM #__dharmashop_group_' . $table . ' WHERE group_id IN (' . $in . ')')->execute();
            }

            $db->setQuery(
                'INSERT INTO #__dharmashop_group_listing (catid, group_id, sort_order, price_min, price_max, created, title_sort, in_stock) ' . $this->listingSelect($in)
            )->execute();

            $db->setQuery('INSERT IGNORE INTO #__dharmashop_group_keys (group_id, kind, ref, opt) ' . $this->keysSelect($in))->execute();
            $db->setQuery('INSERT INTO #__dharmashop_group_bounds (group_id, ref, num_min, num_max) ' . $this->boundsSelect($in))->execute();
            $db->transactionCommit(true);
        } catch (\Throwable $error) {
            $db->transactionRollback(true);

            throw $error;
        }
    }

    /** Доступные участники опубликованных групп: опубликованный товар в опубликованной ветке категорий витрины. */
    private function members(string $in): string
    {
        $catalog = $this->db->quote('com_dharmashop');

        return 'SELECT m.id, m.group_id, m.price_min, m.price_max, m.created, m.in_stock FROM #__dharmashop_products m '
            . "INNER JOIN #__categories mc ON mc.id = m.catid AND mc.extension = $catalog AND mc.published = 1 AND " . $this->chain('mc', $catalog)
            . " WHERE m.state = 1 AND m.group_id IN ($in)";
    }

    /** Нет неопубликованного предка (или самой категории) того же типа. */
    private function chain(string $alias, string $extension): string
    {
        return "NOT EXISTS (SELECT 1 FROM #__categories b WHERE b.extension = $extension AND b.lft <= $alias.lft AND b.rgt >= $alias.rgt AND b.published <> 1)";
    }

    /** Строки индекса по участникам: те же правила видимости и сортировки, что и у запроса со списком групп. */
    private function listingSelect(string $in): string
    {
        $db = $this->db;
        $catalog = $db->quote('com_dharmashop');
        $extensions = implode(',', array_map([$db, 'quote'], ['com_dharmashop', 'com_dharmashop.brand', 'com_dharmashop.badge']));
        $members = $this->members($in);
        $placements = "SELECT g2.id AS group_id, 0 AS catid FROM #__dharmashop_product_groups g2 WHERE g2.id IN ($in) "
            . 'UNION ALL SELECT DISTINCT lgt.group_id, anc.id FROM #__dharmashop_group_categories lgt '
            . "INNER JOIN #__categories lga ON lga.id = lgt.catid AND lga.extension IN ($extensions) AND lga.published = 1 AND " . $this->chain('lga', 'lga.extension')
            . ' INNER JOIN #__categories anc ON anc.extension = lga.extension AND anc.lft <= lga.lft AND anc.rgt >= lga.rgt AND anc.level > 0'
            . " WHERE lgt.group_id IN ($in)";

        return 'SELECT x.catid, g.id AS group_id, '
            . 'MIN(IF(x.catid = 0, m.id, COALESCE(pcx.sort_order, m.id))) AS sort_order, '
            . 'IF(SUM(m.price_min IS NULL) > 0, NULL, MIN(m.price_min)) AS price_min, MAX(m.price_max) AS price_max, MAX(m.created) AS created, '
            . 'LEFT(LOWER(g.title), 60) AS title_sort, MAX(m.in_stock) AS in_stock '
            . 'FROM #__dharmashop_product_groups g '
            . 'LEFT JOIN #__categories gc ON gc.id = g.catid '
            . "INNER JOIN ($placements) x ON x.group_id = g.id "
            . "INNER JOIN ($members) m ON m.group_id = g.id "
            . 'LEFT JOIN #__dharmashop_product_categories pcx ON pcx.product_id = m.id AND pcx.catid = x.catid AND pcx.state = 1 '
            . "WHERE g.id IN ($in) AND g.state = 1 AND ((g.catid = 1 AND gc.extension = 'system' AND gc.parent_id = 0) OR (gc.extension = $catalog AND gc.published = 1 AND " . $this->chain('gc', $catalog) . ')) '
            . 'GROUP BY x.catid, g.id';
    }

    /** Ключи значений участников групп: значения полей, бренды, бейджи и категории витрины. */
    private function keysSelect(string $in): string
    {
        $db = $this->db;
        $members = $this->members($in);
        $brand = $db->quote('com_dharmashop.brand');
        $badge = $db->quote('com_dharmashop.badge');
        $catalog = $db->quote('com_dharmashop');

        return "SELECT DISTINCT m.group_id, 1 AS kind, fv.field_id AS ref, fv.option_id AS opt FROM ($members) m INNER JOIN #__dharmashop_field_values fv ON fv.product_id = m.id WHERE fv.option_id IS NOT NULL "
            . "UNION SELECT DISTINCT m.group_id, CASE c.extension WHEN $brand THEN 2 WHEN $badge THEN 3 ELSE 4 END, pc.catid, 0 FROM ($members) m "
            . 'INNER JOIN #__dharmashop_product_categories pc ON pc.product_id = m.id AND pc.state = 1 INNER JOIN #__categories c ON c.id = pc.catid '
            . "WHERE c.extension = $catalog OR (c.extension IN ($brand, $badge) AND pc.direct = 1)";
    }

    /** Границы чисел (по полю) и цены (ref 0) участников групп. */
    private function boundsSelect(string $in): string
    {
        $members = $this->members($in);

        return "SELECT m.group_id, fv.field_id AS ref, MIN(fv.num) AS num_min, MAX(COALESCE(fv.num_end, fv.num)) AS num_max FROM ($members) m "
            . 'INNER JOIN #__dharmashop_field_values fv ON fv.product_id = m.id WHERE fv.num IS NOT NULL GROUP BY m.group_id, fv.field_id '
            . "UNION ALL SELECT m.group_id, 0, MIN(m.price_min), MAX(m.price_max) FROM ($members) m WHERE m.price_min > 0 GROUP BY m.group_id";
    }

    /**
     * @param   string[]  $keys
     * @param   string[]  $values
     *
     * @return  array<string, string>  ключ «столбец:столбец» → значения строки одной строкой
     */
    private function snapshotOf(string $sql, array $keys, array $values): array
    {
        $out = [];

        foreach ($this->db->setQuery($sql)->loadObjectList() as $row) {
            $key = implode(':', array_map(static fn (string $k): string => (string) $row->$k, $keys));
            $out[$key] = implode('|', array_map(static fn (string $v): string => in_array($v, ['price_min', 'price_max', 'num_min', 'num_max'], true) ? (string) ($row->$v === null ? 'N' : (float) $row->$v) : (string) $row->$v, $values));
        }

        return $out;
    }
}
