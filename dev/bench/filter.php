<?php
// Dev-only: замеры фильтра (F0, docs/FILTER.md §9) на таблицах bn_* из dev/bench/bench.php.
//
//   php dev/bench/bench.php generate <N>      сначала создать каталог
//   php dev/bench/filter.php run <N>          замерить формы условий, подсчёт значений, предрасчёт; результат — dev/bench/results/filter-<N>.json
//   php dev/bench/filter.php report           собрать docs/BENCHMARKS_FILTER.md из results/filter-*.json
//
// Данные только в таблицах bn_*; временные таблицы (bn_filter_facets) и пробные индексы создаются и убираются самим скриптом.
require __DIR__ . '/../boot.php';

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$cmd = $argv[1] ?? '';
$n = (int) ($argv[2] ?? 0);
$root = dirname(__DIR__, 2);

if ($cmd === 'report') {
    require __DIR__ . '/filter-report.php';
    exit;
}

if ($cmd !== 'run' || $n < 1) {
    fwrite(STDERR, "usage: php dev/bench/filter.php run <N> | report\n");
    exit(1);
}

function q(\Joomla\Database\DatabaseDriver $db, string $sql): void
{
    $db->setQuery($sql)->execute();
}

function flags(\Joomla\Database\DatabaseDriver $db, string $sql): string
{
    $out = [];
    foreach ($db->setQuery('EXPLAIN ' . $sql)->loadAssocList() as $r) {
        $extra = (string) ($r['Extra'] ?? '');
        if (stripos($extra, 'filesort') !== false) {
            $out['filesort'] = 1;
        }
        if (stripos($extra, 'temporary') !== false) {
            $out['temp'] = 1;
        }
        if (($r['type'] ?? '') === 'ALL' && !str_starts_with((string) ($r['table'] ?? ''), '<derived')) {
            $out['scan:' . $r['table']] = 1;
        }
    }

    return implode(',', array_keys($out));
}

/** 2 прогрева + 7 прогонов: медиана и максимум, мс. */
function timeit(callable $fn): array
{
    for ($i = 0; $i < 2; $i++) {
        $fn();
    }
    $t = [];
    for ($i = 0; $i < 7; $i++) {
        $s = hrtime(true);
        $fn();
        $t[] = (hrtime(true) - $s) / 1e6;
    }
    sort($t);

    return ['ms' => round($t[3], 2), 'max' => round($t[6], 2)];
}

$results = ['n' => $n, 'date' => date('c'), 'rows' => []];
$add = static function (string $group, string $name, callable $fn, ?string $sql = null) use (&$results, $db): void {
    $m = timeit($fn);
    $row = ['group' => $group, 'name' => $name] + $m + ['plan' => $sql !== null ? flags($db, $sql) : ''];
    $results['rows'][] = $row;
    printf("%-9s %-62s %8.2f мс (max %8.2f) %s\n", $group, $name, $row['ms'], $row['max'], $row['plan']);
};

// Размеры и распределение (для выбора ведущего условия — как будет делать приложение по базовому состоянию).
$results['stats'] = [
    'products' => (int) $db->setQuery('SELECT COUNT(*) FROM bn_products')->loadResult(),
    'field_values' => (int) $db->setQuery('SELECT COUNT(*) FROM bn_field_values')->loadResult(),
    'product_categories' => (int) $db->setQuery('SELECT COUNT(*) FROM bn_product_categories')->loadResult(),
];
$est = $db->setQuery('SELECT field_id, option_id, COUNT(*) c FROM bn_field_values WHERE option_id IS NOT NULL GROUP BY field_id, option_id')->loadObjectList();
$count = [];
foreach ($est as $e) {
    $count[(int) $e->option_id] = (int) $e->c;
}
$estimate = static fn (array $opts): int => array_sum(array_map(static fn (int $o): int => $count[$o] ?? 0, $opts));

// ---- Условия: описание сценариев ------------------------------------------------------------------------------------------------------------------
// opt: поле-список; and: все значения (логика «И»); num: диапазон числа; price: цена.
$brandTop = [1];            // самое частое значение бренда (перекос)
$brandMid = [3, 4];
$material = [33, 36];
$color = [130, 131];        // поле варианта
$size = [141, 142];         // поле варианта
$country = [45, 46];
$tagsA = [91];              // multilist
$scenarios = [
    'S1 бренд (3,4)' => [['opt', 1, $brandMid]],
    'S2 бренд самый частый' => [['opt', 1, $brandTop]],
    'S3 цена+бренд+материал+цвет' => [['price', 5000, 40000], ['opt', 1, $brandMid], ['opt', 2, $material], ['opt', 8, $color]],
    'S4 неудобный: частый бренд+материал+страна' => [['opt', 1, $brandTop], ['opt', 2, [31]], ['opt', 3, $country]],
    'S5 редкий: цвет+размер (варианты)+тег' => [['opt', 8, $color], ['opt', 9, $size], ['opt', 7, $tagsA]],
    'S6 число+цена+бренд' => [['num', 10, 5, 20], ['price', 1000, 90000], ['opt', 1, $brandMid]],
    'S7 логика И по тегам' => [['and', 7, [91, 92]]],
];
$cats = ['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12, 'leaf-hot(112)' => 112];

// ---- Построители условий ------------------------------------------------------------------------------------------------------------------------
$exists = static function (array $f, int $k): string {
    return match ($f[0]) {
        'opt' => "EXISTS (SELECT 1 FROM bn_field_values v$k WHERE v$k.product_id = pc.product_id AND v$k.field_id = {$f[1]} AND v$k.option_id IN (" . implode(',', $f[2]) . '))',
        'and' => implode(' AND ', array_map(static fn (int $o): string => "EXISTS (SELECT 1 FROM bn_field_values v{$k}x$o WHERE v{$k}x$o.product_id = pc.product_id AND v{$k}x$o.field_id = {$f[1]} AND v{$k}x$o.option_id = $o)", $f[2])),
        'num' => "EXISTS (SELECT 1 FROM bn_field_values v$k WHERE v$k.product_id = pc.product_id AND v$k.field_id = {$f[1]} AND v$k.num BETWEEN {$f[2]} AND {$f[3]})",
        'price' => "pc.price_sort <= {$f[2]} AND p.price_max >= {$f[1]}",
    };
};
$subq = static function (array $f): ?string {
    return match ($f[0]) {
        'opt' => 'SELECT product_id FROM bn_field_values WHERE field_id = ' . $f[1] . ' AND option_id IN (' . implode(',', $f[2]) . ')',
        'num' => 'SELECT product_id FROM bn_field_values WHERE field_id = ' . $f[1] . ' AND num BETWEEN ' . $f[2] . ' AND ' . $f[3],
        default => null,
    };
};
$estimateOf = static function (array $f) use ($estimate): int {
    return match ($f[0]) {
        'opt', 'and' => $f[0] === 'and' ? (int) ($estimate([$f[2][0]]) / 8) : $estimate($f[2]),
        'num' => 1 << 28,
        default => 1 << 30,
    };
};

$order = 'pc.sort_order, pc.product_id';
$orderPrice = 'pc.price_sort, pc.product_id';

/** A: условия EXISTS (базовая форма). */
$formA = static function (int $cat, array $filters, string $ord) use ($exists): string {
    $w = [];
    foreach ($filters as $k => $f) {
        $w[] = $exists($f, $k);
    }

    return "SELECT pc.product_id FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND " . implode(' AND ', $w) . " ORDER BY $ord LIMIT 24";
};
/** B: самое избирательное условие — ведущий IN-подзапрос, остальные EXISTS. */
$formB = static function (int $cat, array $filters, string $ord) use ($exists, $subq, $estimateOf): string {
    $lead = null;
    $best = PHP_INT_MAX;
    foreach ($filters as $k => $f) {
        if ($subq($f) !== null && $estimateOf($f) < $best) {
            $best = $estimateOf($f);
            $lead = $k;
        }
    }
    $w = [];
    foreach ($filters as $k => $f) {
        $w[] = $k === $lead ? 'pc.product_id IN (' . $subq($f) . ')' : $exists($f, $k);
    }

    return "SELECT pc.product_id FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND " . implode(' AND ', $w) . " ORDER BY $ord LIMIT 24";
};
/** C: соединение с производными таблицами DISTINCT по каждому условию-значению. */
$formC = static function (int $cat, array $filters, string $ord) use ($exists, $subq): string {
    $joins = '';
    $w = [];
    foreach ($filters as $k => $f) {
        if ($subq($f) !== null) {
            $joins .= " JOIN (SELECT DISTINCT product_id FROM (" . $subq($f) . ") z$k) d$k ON d$k.product_id = pc.product_id";
        } else {
            $w[] = $exists($f, $k);
        }
    }

    return "SELECT pc.product_id FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1$joins WHERE pc.catid = $cat AND pc.state = 1" . ($w ? ' AND ' . implode(' AND ', $w) : '') . " ORDER BY $ord LIMIT 24";
};
/** D: ведущая выборка из значений полей, затем витрина по первичному ключу (STRAIGHT_JOIN). */
$formD = static function (int $cat, array $filters, string $ord) use ($exists, $subq, $estimateOf): string {
    $lead = null;
    $best = PHP_INT_MAX;
    foreach ($filters as $k => $f) {
        if ($subq($f) !== null && $estimateOf($f) < $best) {
            $best = $estimateOf($f);
            $lead = $k;
        }
    }
    if ($lead === null) {
        return $GLOBALS['formA']($cat, $filters, $ord);
    }
    $w = [];
    foreach ($filters as $k => $f) {
        if ($k !== $lead) {
            $w[] = $exists($f, $k);
        }
    }

    return 'SELECT STRAIGHT_JOIN pc.product_id FROM (SELECT DISTINCT product_id FROM (' . $subq($filters[$lead]) . ") lz) lead_ids JOIN bn_product_categories pc ON pc.catid = $cat AND pc.product_id = lead_ids.product_id AND pc.state = 1 JOIN bn_products p ON p.id = pc.product_id AND p.state = 1"
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY $ord LIMIT 24";
};
$GLOBALS['formA'] = $formA;
$forms = ['A EXISTS' => $formA, 'B ведущий IN' => $formB, 'C JOIN DISTINCT' => $formC, 'D ведущая выборка' => $formD];

// ===== 1. Формы условий: список страницы =====================================================================================================================
echo "== 1. Формы условий (список, 24 позиции) ==\n";
foreach ($scenarios as $sname => $filters) {
    foreach (['root(1)' => 1, 'L2(12)' => 12] as $clabel => $cat) {
        foreach ($forms as $fname => $form) {
            $sql = $form($cat, $filters, $order);
            $add('forms', "$sname / $clabel / $fname", static function () use ($db, $sql) {
                $db->setQuery($sql)->loadColumn();
            }, $sql);
        }
    }
}
// Совпадают ли результаты форм (корректность).
$bad = [];
foreach ($scenarios as $sname => $filters) {
    $ref = null;
    foreach ($forms as $fname => $form) {
        $ids = $db->setQuery($form(1, $filters, $order))->loadColumn();
        $ref ??= $ids;
        if ($ids !== $ref) {
            $bad[] = "$sname: $fname";
        }
    }
}
$results['consistency'] = $bad ?: 'ok';
echo 'Согласованность форм: ' . (is_array($results['consistency']) ? implode('; ', $results['consistency']) : 'ok') . "\n";

// ===== 2. Число найденных с потолком =========================================================================================================================
echo "== 2. Счёт с потолком 10001 ==\n";
foreach (['S3 цена+бренд+материал+цвет', 'S4 неудобный: частый бренд+материал+страна', 'S2 бренд самый частый'] as $sname) {
    $filters = $scenarios[$sname];
    foreach (['A EXISTS' => $formA, 'B ведущий IN' => $formB] as $fname => $form) {
        $inner = preg_replace('/^SELECT pc\.product_id/', 'SELECT 1', preg_replace('/ ORDER BY .* LIMIT 24$/', ' LIMIT 10001', $form(1, $filters, $order)));
        $sql = "SELECT COUNT(*) FROM ($inner) t";
        $add('count', "$sname / root / $fname", static function () use ($db, $sql) {
            $db->setQuery($sql)->loadResult();
        }, $sql);
    }
}

// ===== 3. Пробный индекс для EXISTS: (product_id, field_id, option_id) ======================================================================================
echo "== 3. Покрывающий индекс для EXISTS ==\n";
$indexes = array_column($db->setQuery('SHOW INDEX FROM bn_field_values')->loadAssocList(), 'Key_name');
if (!in_array('idx_cov', $indexes, true)) {
    $t = microtime(true);
    q($db, 'ALTER TABLE bn_field_values ADD KEY idx_cov (product_id, field_id, option_id, num)');
    $results['index_build_s'] = round(microtime(true) - $t, 1);
    q($db, 'ANALYZE TABLE bn_field_values');
    printf("индекс idx_cov построен за %.1f с\n", $results['index_build_s']);
}
foreach (['S3 цена+бренд+материал+цвет', 'S4 неудобный: частый бренд+материал+страна', 'S5 редкий: цвет+размер (варианты)+тег'] as $sname) {
    foreach (['A EXISTS' => $formA, 'B ведущий IN' => $formB] as $fname => $form) {
        $sql = $form(1, $scenarios[$sname], $order);
        $add('cov-index', "$sname / root / $fname (с idx_cov)", static function () use ($db, $sql) {
            $db->setQuery($sql)->loadColumn();
        }, $sql);
    }
}

// ===== 4. Значения и счётчики =================================================================================================================================
echo "== 4. Значения и счётчики (список-поля 1–6, база и каскад) ==\n";
$list = '1,2,3,4,5,6';
foreach (['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12] as $clabel => $cat) {
    // база: одно поле / все сразу
    $one = "SELECT v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 1 WHERE pc.catid = $cat AND pc.state = 1 GROUP BY v.option_id";
    $add('facets', "база, 1 поле (бренд) / $clabel", static fn () => $db->setQuery($one)->loadAssocList(), $one);
    $all = "SELECT v.field_id, v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id IN ($list) WHERE pc.catid = $cat AND pc.state = 1 GROUP BY v.field_id, v.option_id";
    $add('facets', "база, 6 полей одним запросом / $clabel", static fn () => $db->setQuery($all)->loadAssocList(), $all);
    // каскад: выбран бренд (3,4); числа остальных полей
    $cas = "SELECT v.field_id, v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id IN (2,3,4,5,6) WHERE pc.catid = $cat AND pc.state = 1 AND EXISTS (SELECT 1 FROM bn_field_values b WHERE b.product_id = pc.product_id AND b.field_id = 1 AND b.option_id IN (3,4)) GROUP BY v.field_id, v.option_id";
    $add('facets', "каскад: выбран бренд (3,4), 5 полей / $clabel", static fn () => $db->setQuery($cas)->loadAssocList(), $cas);
    $casTop = str_replace('IN (3,4)', 'IN (1)', $cas);
    $add('facets', "каскад: выбран частый бренд, 5 полей / $clabel", static fn () => $db->setQuery($casTop)->loadAssocList(), $casTop);
    // каскад по нескольким выбранным: бренд + материал + цвет(вариант); числа страны
    $cas3 = "SELECT v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 3 WHERE pc.catid = $cat AND pc.state = 1"
        . " AND EXISTS (SELECT 1 FROM bn_field_values b WHERE b.product_id = pc.product_id AND b.field_id = 1 AND b.option_id IN (3,4))"
        . " AND EXISTS (SELECT 1 FROM bn_field_values m WHERE m.product_id = pc.product_id AND m.field_id = 2 AND m.option_id IN (33,36))"
        . " AND EXISTS (SELECT 1 FROM bn_field_values c WHERE c.product_id = pc.product_id AND c.field_id = 8 AND c.option_id IN (130,131)) GROUP BY v.option_id";
    $add('facets', "каскад: 3 условия, поле «страна» / $clabel", static fn () => $db->setQuery($cas3)->loadAssocList(), $cas3);
    // поле варианта с DISTINCT
    $var = "SELECT v.option_id, COUNT(DISTINCT pc.product_id) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 8 WHERE pc.catid = $cat AND pc.state = 1 GROUP BY v.option_id";
    $add('facets', "база, поле варианта (цвет) DISTINCT / $clabel", static fn () => $db->setQuery($var)->loadAssocList(), $var);
    // порог кандидатов
    $cap = "SELECT COUNT(*) FROM (SELECT 1 FROM bn_product_categories WHERE catid = $cat AND state = 1 LIMIT 20001) t";
    $add('facets', "проверка порога кандидатов (20 001) / $clabel", static fn () => $db->setQuery($cap)->loadResult(), $cap);
    // границы
    $pb = "SELECT MIN(price_sort), MAX(price_sort) FROM bn_product_categories WHERE catid = $cat AND state = 1";
    $add('facets', "границы цены по индексу / $clabel", static fn () => $db->setQuery($pb)->loadRow(), $pb);
    $nb = "SELECT MIN(v.num), MAX(v.num) FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 10 WHERE pc.catid = $cat AND pc.state = 1";
    $add('facets', "границы числа (вес) живые / $clabel", static fn () => $db->setQuery($nb)->loadRow(), $nb);
}

// ===== 5. Предрасчёт базового состояния ======================================================================================================================
echo "== 5. Предрасчёт (bn_filter_facets) ==\n";
q($db, 'DROP TABLE IF EXISTS bn_filter_facets');
q($db, 'CREATE TABLE bn_filter_facets (catid int unsigned NOT NULL, field_id int unsigned NOT NULL, option_id int unsigned NOT NULL, products int unsigned NOT NULL, PRIMARY KEY (catid, field_id, option_id)) ENGINE=InnoDB');
$t = microtime(true);
q($db, "INSERT INTO bn_filter_facets SELECT pc.catid, v.field_id, v.option_id, COUNT(DISTINCT pc.product_id) FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id WHERE pc.state = 1 AND v.option_id IS NOT NULL GROUP BY pc.catid, v.field_id, v.option_id");
$results['facets_build_s'] = round(microtime(true) - $t, 1);
$results['facets_rows'] = (int) $db->setQuery('SELECT COUNT(*) FROM bn_filter_facets')->loadResult();
printf("полная сборка: %.1f с, строк %d\n", $results['facets_build_s'], $results['facets_rows']);
foreach (['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12] as $clabel => $cat) {
    $sql = "SELECT field_id, option_id, products FROM bn_filter_facets WHERE catid = $cat";
    $add('precalc', "чтение базового состояния (все поля) / $clabel", static fn () => $db->setQuery($sql)->loadAssocList(), $sql);
}
// Сопровождение: изменение одного товара (+1/−1 по строкам витрины товара).
$pid = max(1, intdiv($n, 3));
$upd = "INSERT INTO bn_filter_facets (catid, field_id, option_id, products) SELECT pc.catid, v.field_id, v.option_id, 1 FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id WHERE pc.product_id = $pid AND pc.state = 1 AND v.option_id IS NOT NULL GROUP BY pc.catid, v.field_id, v.option_id ON DUPLICATE KEY UPDATE products = products + 1";
$rev = str_replace('products = products + 1', 'products = GREATEST(products - 1, 0)', $upd);
$add('precalc', 'сопровождение: +1 по строкам одного товара', static function () use ($db, $upd, $rev) {
    $db->setQuery($upd)->execute();
    $db->setQuery($rev)->execute();
});
$results['facets_rows_per_product'] = (int) $db->setQuery("SELECT COUNT(*) FROM (SELECT pc.catid, v.field_id, v.option_id FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id WHERE pc.product_id = $pid AND pc.state = 1 AND v.option_id IS NOT NULL GROUP BY pc.catid, v.field_id, v.option_id) x")->loadResult();
printf("строк предрасчёта на один товар: %d\n", $results['facets_rows_per_product']);
q($db, 'DROP TABLE IF EXISTS bn_filter_facets');

// Убираем пробный индекс (стенд возвращается к схеме проекта).
q($db, 'ALTER TABLE bn_field_values DROP KEY idx_cov');

@mkdir(__DIR__ . '/results');
file_put_contents(__DIR__ . "/results/filter-$n.json", json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nГотово: dev/bench/results/filter-$n.json\n";
