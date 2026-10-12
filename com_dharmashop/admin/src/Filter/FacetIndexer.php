<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Предрасчёт базового состояния фильтра (C84, замеры F0): `filter_facets (catid, field_id, option_id, products)` — сколько товаров категории
 * имеют значение поля — и `filter_ranges` — границы чисел. Чтение значений категории — один индексный запрос (0,4 мс вместо 67–399 мс «живьём»).
 *
 * Считаются только опубликованные поля-списки с `filterable = 1`, товары витрины с `state = 1` (как список), товар — один раз на значение
 * (поле варианта с одинаковым цветом у двух вариантов не удваивает). Сопровождение — относительное: `track()` снимает набор строк товара до и
 * после правки и применяет разницу (+1/−1); полная и частичная пересборка — командой «Счётчики фильтра» и при смене настроек поля.
 * Данные производные: источник правды — `field_values` и `product_categories`, `verify()` сверяет таблицу с живым подсчётом.
 */
final class FacetIndexer
{
    /** Предел товаров в одном `track()`: больше — слишком дорого, нужна пересборка. */
    private const TRACK_LIMIT = 300;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** Условия «поле участвует в фильтре» (списки; числа — для границ). */
    private function eligible(string $alias, string $stores): string
    {
        return "$alias.target = 'product' AND $alias.state = 1 AND $alias.filterable = 1 AND $alias.store IN ($stores)";
    }

    /**
     * Полная пересборка порциями по категориям; укладывается в бюджет времени и возвращает курсор (номер категории), с которого продолжить.
     *
     * @return  array{processed: int, cursor: int, done: bool}
     */
    public function rebuildStep(int $cursorCatid, int $budgetMs = 8000, int $categoriesPerChunk = 25): array
    {
        $db = $this->db;
        $start = hrtime(true);
        $processed = 0;

        if ($cursorCatid <= 0) {
            $this->rebuildCategories([0]);
        }

        while (true) {
            $query = $db->createQuery()->select('DISTINCT catid')->from('(SELECT catid FROM #__dharmashop_product_categories UNION SELECT catid FROM #__dharmashop_filter_facets UNION SELECT catid FROM #__dharmashop_filter_ranges) AS rebuild_cats')
                ->where('catid > :c')->order('catid')->bind(':c', $cursorCatid, ParameterType::INTEGER);
            $catids = array_map('intval', $db->setQuery($query, 0, $categoriesPerChunk)->loadColumn());

            if (!$catids) {
                return ['processed' => $processed, 'cursor' => 0, 'done' => true];
            }

            $this->rebuildCategories($catids);
            $cursorCatid = (int) end($catids);
            $processed += count($catids);

            if ((hrtime(true) - $start) / 1e6 >= $budgetMs) {
                return ['processed' => $processed, 'cursor' => $cursorCatid, 'done' => false];
            }
        }
    }

    /** Полная пересборка за один вызов (консоль, тесты, небольшие каталоги). Возвращает число строк значений. */
    public function rebuildAll(): int
    {
        $cursor = 0;

        do {
            $step = $this->rebuildStep($cursor, PHP_INT_MAX);
            $cursor = $step['cursor'];
        } while (!$step['done']);

        FilterCache::forget();
        return (int) $this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->quoteName('#__dharmashop_filter_facets'))->loadResult();
    }

    /** Пересчёт для перечисленных категорий (после перемещения ветки, смены состояния категории). @param int[] $catids */
    public function rebuildCategories(array $catids): void
    {
        $catids = array_values(array_unique(array_filter(array_map('intval', $catids), static fn(int $id): bool => $id >= 0)));

        if (!$catids) {
            return;
        }

        $list = implode(',', $catids);
        $this->db->transactionStart(true);
        try {
            $this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__dharmashop_filter_facets') . " WHERE catid IN ($list)")->execute();
            $this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__dharmashop_filter_ranges') . " WHERE catid IN ($list)")->execute();
            $this->insertFor($catids);
            $this->db->transactionCommit(true);
        } catch (\Throwable $error) { $this->db->transactionRollback(true); throw $error; }
        FilterCache::forget();
    }

    /** Пересчёт одного поля во всех категориях (после смены флага «фильтр», удаления поля или его значений). */
    public function rebuildField(int $fieldId): void
    {
        $db = $this->db;
        $db->transactionStart(true);
        try {
            $db->setQuery('DELETE FROM ' . $db->quoteName('#__dharmashop_filter_facets') . ' WHERE field_id = ' . (int) $fieldId)->execute();
            $db->setQuery('DELETE FROM ' . $db->quoteName('#__dharmashop_filter_ranges') . ' WHERE field_id = ' . (int) $fieldId)->execute();
            $this->insertFor(null, $fieldId);
            $db->transactionCommit(true);
        } catch (\Throwable $error) { $db->transactionRollback(true); throw $error; }
        FilterCache::forget();
    }

    /**
     * Выполняет изменение товаров и сопровождает таблицы разницей наборов строк «категория — поле — значение» до и после.
     * Вызывать вокруг записи значений полей, категорий и состояния товара (в той же транзакции, что и запись).
     *
     * @param   int[]     $productIds
     * @param   callable  $mutation   изменение; его результат возвращается
     */
    public function track(array $productIds, callable $mutation): mixed
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$productIds) {
            return $mutation();
        }

        $token = $this->begin($productIds);
        $result = $mutation();
        $this->end($token);

        return $result;
    }

    /**
     * Первая половина `track()` для кода, где изменение нельзя обернуть функцией: снимок наборов строк товаров до правки.
     *
     * @param   int[]  $productIds
     *
     * @return  array{ids: int[], before: array<int, array{facets: array<string, true>, ranges: array<string, true>}>}|null  null — товаров слишком много (будет сброс кэша)
     */
    public function begin(array $productIds): ?array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$productIds) { return null; }
        if (count($productIds) > self::TRACK_LIMIT) {
            return ['ids' => $productIds, 'before' => [], 'categories' => $this->affectedCategories($productIds)];
        }

        return ['ids' => $productIds, 'before' => $this->snapshot($productIds)];
    }

    /** Вторая половина: снимок после правки, применение разницы и сброс кэша. @param array{ids: int[], before: array}|null $token */
    public function end(?array $token): void
    {
        if (isset($token['categories'])) { $this->rebuildCategories(array_merge($token['categories'], $this->affectedCategories($token['ids']))); }
        elseif ($token !== null) {
            $this->apply($token['before'], $this->snapshot($token['ids']));
        }

        FilterCache::forget();
    }

    private function affectedCategories(array $ids): array
    {
        $categories = [0];
        foreach (array_chunk($ids,500) as $chunk) { $categories = array_merge($categories, array_map('intval', $this->db->setQuery('SELECT DISTINCT catid FROM #__dharmashop_product_categories WHERE product_id IN ('.implode(',',$chunk).')')->loadColumn())); }
        return array_values(array_unique($categories));
    }

    /**
     * Сверка таблицы с живым подсчётом для выборки категорий; возвращает расхождения (пусто — согласовано).
     *
     * @return  array<int, array{catid: int, field_id: int, option_id: int, table: int, live: int}>
     */
    public function verify(int $categories = 20): array
    {
        $db = $this->db;
        $catids = array_map('intval', $db->setQuery('SELECT catid FROM (SELECT 0 AS catid UNION SELECT catid FROM #__dharmashop_product_categories UNION SELECT catid FROM #__dharmashop_filter_facets UNION SELECT catid FROM #__dharmashop_filter_ranges) cats ORDER BY catid', 0, max(1,min(10000,$categories)))->loadColumn());
        $bad = [];

        foreach ($catids as $catid) {
            $live = [];

            foreach ($this->liveRows((int) $catid) as $row) {
                $live[(int) $row->field_id . ':' . (int) $row->option_id] = (int) $row->products;
            }

            $query = $db->createQuery()->select(['field_id', 'option_id', 'products'])->from($db->quoteName('#__dharmashop_filter_facets'))
                ->where('catid = :c')->bind(':c', $catid, ParameterType::INTEGER);

            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $key = (int) $row->field_id . ':' . (int) $row->option_id;
                $expected = $live[$key] ?? 0;
                unset($live[$key]);

                if ((int) $row->products !== $expected) {
                    $bad[] = ['catid' => (int) $catid, 'field_id' => (int) $row->field_id, 'option_id' => (int) $row->option_id, 'table' => (int) $row->products, 'live' => $expected];
                }
            }

            foreach ($live as $key => $count) {
                [$field, $option] = array_map('intval', explode(':', $key));
                $bad[] = ['catid' => (int) $catid, 'field_id' => $field, 'option_id' => $option, 'table' => 0, 'live' => $count];
            }
        }

        foreach ($catids as $catid) {
            $from = $catid ? '#__dharmashop_product_categories pc JOIN #__dharmashop_field_values v ON v.product_id=pc.product_id' : '#__dharmashop_products p JOIN #__dharmashop_field_values v ON v.product_id=p.id';
            $where = $catid ? 'pc.catid=' . $catid . ' AND pc.state=1' : 'p.state=1';
            $live=[];
            foreach ($db->setQuery('SELECT v.field_id,MIN(v.num) mn,MAX(COALESCE(v.num_end,v.num)) mx FROM '.$from.' JOIN #__dharmashop_fields f ON f.id=v.field_id AND '.$this->eligible('f', "'number','range'").' WHERE '.$where.' AND v.num IS NOT NULL GROUP BY v.field_id')->loadObjectList() as $row) { $live[(int)$row->field_id]=[(float)$row->mn,(float)$row->mx]; }
            foreach ($db->setQuery('SELECT field_id,num_min,num_max FROM #__dharmashop_filter_ranges WHERE catid='.$catid)->loadObjectList() as $row) {
                $value=[(float)$row->num_min,(float)$row->num_max];$expected=$live[(int)$row->field_id]??null;unset($live[(int)$row->field_id]);
                if($value!==$expected)$bad[]=['catid'=>$catid,'field_id'=>(int)$row->field_id,'option_id'=>0,'table'=>$value,'live'=>$expected];
            }
            foreach($live as $field=>$value)$bad[]=['catid'=>$catid,'field_id'=>$field,'option_id'=>0,'table'=>null,'live'=>$value];
        }
        return $bad;
    }

    /**
     * @param int[]|null $catids null — все категории; номер 0 — «весь каталог» (страница без категории); $fieldId — только это поле
     */
    private function insertFor(?array $catids, ?int $fieldId = null): void
    {
        $db = $this->db;

        // Весь каталог (catid = 0): товары со state = 1 без витрины.
        if ($catids === null || in_array(0, $catids, true)) {
            $fieldScope = $fieldId !== null ? ' AND v.field_id = ' . (int) $fieldId : '';
            $db->setQuery(
                'INSERT INTO ' . $db->quoteName('#__dharmashop_filter_facets') . ' (catid, field_id, option_id, products) '
                . 'SELECT 0, v.field_id, v.option_id, COUNT(DISTINCT v.product_id) FROM ' . $db->quoteName('#__dharmashop_field_values', 'v')
                . ' JOIN ' . $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id AND p.state = 1'
                . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option'")
                . ' WHERE v.option_id IS NOT NULL' . $fieldScope . ' GROUP BY v.field_id, v.option_id'
            )->execute();
            $db->setQuery(
                'INSERT INTO ' . $db->quoteName('#__dharmashop_filter_ranges') . ' (catid, field_id, num_min, num_max) '
                . 'SELECT 0, v.field_id, MIN(v.num), MAX(COALESCE(v.num_end, v.num)) FROM ' . $db->quoteName('#__dharmashop_field_values', 'v')
                . ' JOIN ' . $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id AND p.state = 1'
                . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'number','range'")
                . ' WHERE v.num IS NOT NULL' . $fieldScope . ' GROUP BY v.field_id'
            )->execute();

            if ($catids !== null) {
                $catids = array_values(array_filter($catids, static fn (int $id): bool => $id !== 0));

                if (!$catids) {
                    return;
                }
            }
        }

        $scope = ($catids !== null ? ' AND pc.catid IN (' . implode(',', array_map('intval', $catids)) . ')' : '') . ($fieldId !== null ? ' AND v.field_id = ' . (int) $fieldId : '');
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__dharmashop_filter_facets') . ' (catid, field_id, option_id, products) '
            . 'SELECT pc.catid, v.field_id, v.option_id, COUNT(DISTINCT pc.product_id) '
            . 'FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = pc.product_id'
            . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option'")
            . ' WHERE pc.state = 1 AND v.option_id IS NOT NULL' . $scope . ' GROUP BY pc.catid, v.field_id, v.option_id'
        )->execute();
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__dharmashop_filter_ranges') . ' (catid, field_id, num_min, num_max) '
            . 'SELECT pc.catid, v.field_id, MIN(v.num), MAX(COALESCE(v.num_end, v.num)) '
            . 'FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = pc.product_id'
            . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'number','range'")
            . ' WHERE pc.state = 1 AND v.num IS NOT NULL' . $scope . ' GROUP BY pc.catid, v.field_id'
        )->execute();
    }

    /** @return array<int, array{facets: array<string, true>, ranges: array<string, true>}> товар → наборы ключей «категория:поле:значение» и «категория:поле» */
    private function snapshot(array $productIds): array
    {
        $db = $this->db;
        $list = implode(',', $productIds);
        $out = [];

        foreach ($productIds as $id) {
            $out[$id] = ['facets' => [], 'ranges' => []];
        }

        $rows = $db->setQuery(
            'SELECT pc.product_id, pc.catid, v.field_id, v.option_id, f.store FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = pc.product_id'
            . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option','number','range'")
            . " WHERE pc.state = 1 AND pc.product_id IN ($list) AND (v.option_id IS NOT NULL OR v.num IS NOT NULL) GROUP BY pc.product_id, pc.catid, v.field_id, v.option_id, f.store"
        )->loadObjectList();

        // Весь каталог (catid = 0) — опубликованные товары без витрины.
        $rows = array_merge($rows, $db->setQuery(
            'SELECT p.id AS product_id, 0 AS catid, v.field_id, v.option_id, f.store FROM ' . $db->quoteName('#__dharmashop_products', 'p')
            . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = p.id'
            . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option','number','range'")
            . " WHERE p.state = 1 AND p.id IN ($list) AND (v.option_id IS NOT NULL OR v.num IS NOT NULL) GROUP BY p.id, v.field_id, v.option_id, f.store"
        )->loadObjectList());

        foreach ($rows as $row) {
            if ($row->store === 'option') {
                $out[(int) $row->product_id]['facets'][(int) $row->catid . ':' . (int) $row->field_id . ':' . (int) $row->option_id] = true;
            } else {
                $out[(int) $row->product_id]['ranges'][(int) $row->catid . ':' . (int) $row->field_id] = true;
            }
        }

        return $out;
    }

    /** @param array<int, array{facets: array<string, true>, ranges: array<string, true>}> $before @param array<int, array{facets: array<string, true>, ranges: array<string, true>}> $after */
    private function apply(array $before, array $after): void
    {
        $db = $this->db;
        $facets = $db->quoteName('#__dharmashop_filter_facets');
        $recompute = [];

        foreach ($before as $id => $old) {
            $new = $after[$id] ?? ['facets' => [], 'ranges' => []];

            foreach (array_diff_key($new['facets'], $old['facets']) as $key => $unused) {
                [$catid, $field, $option] = array_map('intval', explode(':', (string) $key));
                $db->setQuery("INSERT INTO $facets (catid, field_id, option_id, products) VALUES ($catid, $field, $option, 1) ON DUPLICATE KEY UPDATE products = products + 1")->execute();
            }

            foreach (array_diff_key($old['facets'], $new['facets']) as $key => $unused) {
                [$catid, $field, $option] = array_map('intval', explode(':', (string) $key));
                $db->setQuery("UPDATE $facets SET products = GREATEST(products - 1, 0) WHERE catid = $catid AND field_id = $field AND option_id = $option")->execute();
            }

            // Границы чисел пересчитываются живым запросом по затронутым категориям и полям (убрать значение из границ относительно нельзя).
            foreach ([$old['ranges'], $new['ranges']] as $ranges) {
                foreach (array_keys($ranges) as $key) {
                    $recompute[(string) $key] = true;
                }
            }
        }

        foreach (array_keys($recompute) as $key) {
            [$catid, $field] = array_map('intval', explode(':', (string) $key));
            $this->recomputeRange($catid, $field);
        }
    }

    private function recomputeRange(int $catid, int $fieldId): void
    {
        $db = $this->db;
        $ranges = $db->quoteName('#__dharmashop_filter_ranges');
        $row = $db->setQuery($catid === 0
            ? 'SELECT MIN(v.num) AS mn, MAX(COALESCE(v.num_end, v.num)) AS mx FROM ' . $db->quoteName('#__dharmashop_products', 'p')
                . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = p.id AND v.field_id = ' . $fieldId . ' WHERE p.state = 1 AND v.num IS NOT NULL'
            : 'SELECT MIN(v.num) AS mn, MAX(COALESCE(v.num_end, v.num)) AS mx FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
                . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = pc.product_id AND v.field_id = ' . $fieldId
                . " WHERE pc.catid = $catid AND pc.state = 1 AND v.num IS NOT NULL")->loadObject();

        if ($row === null || $row->mn === null) {
            $db->setQuery("DELETE FROM $ranges WHERE catid = $catid AND field_id = $fieldId")->execute();

            return;
        }

        $db->setQuery("INSERT INTO $ranges (catid, field_id, num_min, num_max) VALUES ($catid, $fieldId, " . $db->quote((string) $row->mn) . ', ' . $db->quote((string) $row->mx)
            . ') ON DUPLICATE KEY UPDATE num_min = VALUES(num_min), num_max = VALUES(num_max)')->execute();
    }

    /** Живой подсчёт по категории — для сверки. @return object[] */
    private function liveRows(int $catid): array
    {
        $db = $this->db;

            if ($catid === 0) {
                return $db->setQuery(
                    'SELECT v.field_id, v.option_id, COUNT(DISTINCT p.id) AS products FROM ' . $db->quoteName('#__dharmashop_products', 'p')
                    . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = p.id'
                    . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option'")
                    . ' WHERE p.state = 1 AND v.option_id IS NOT NULL GROUP BY v.field_id, v.option_id'
                )->loadObjectList();
            }

            return $db->setQuery(
                'SELECT v.field_id, v.option_id, COUNT(DISTINCT pc.product_id) AS products FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
                . ' JOIN ' . $db->quoteName('#__dharmashop_field_values', 'v') . ' ON v.product_id = pc.product_id'
                . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id AND ' . $this->eligible('f', "'option'")
                . " WHERE pc.catid = $catid AND pc.state = 1 AND v.option_id IS NOT NULL GROUP BY v.field_id, v.option_id"
            )->loadObjectList();

    }
}
