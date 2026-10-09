<?php
// Dev-only: второй раунд замеров фильтра (F0, docs/FILTER.md §9): выбор формы запроса по плотности условия.
//
//   php dev/bench/filter2.php run <N>      → dev/bench/results/filter2-<N>.json (таблицы bn_*, сначала bench.php generate <N>)
//
// Идея: при плотном условии (значение есть у заметной доли товаров) быстрее идти по индексу сортировки категории и
// останавливаться на 24-м найденном (FORCE INDEX + EXISTS); при редком — выбрать ведущие товары по значению поля и сортировать их.
require __DIR__ . '/../boot.php';

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$n = (int) ($argv[2] ?? 0);
if (($argv[1] ?? '') !== 'run' || $n < 1) {
    fwrite(STDERR, "usage: php dev/bench/filter2.php run <N>\n");
    exit(1);
}

function q2(\Joomla\Database\DatabaseDriver $db, string $sql): void
{
    $db->setQuery($sql)->execute();
}

function flags2(\Joomla\Database\DatabaseDriver $db, string $sql): string
{
    $out = [];
    foreach ($db->setQuery('EXPLAIN ' . $sql)->loadAssocList() as $r) {
        $extra = (string) ($r['Extra'] ?? '');
        if (stripos($extra, 'filesort') !== false) {
            $out['F'] = 1;
        }
        if (stripos($extra, 'temporary') !== false) {
            $out['T'] = 1;
        }
        if (($r['type'] ?? '') === 'ALL' && !str_starts_with((string) ($r['table'] ?? ''), '<derived')) {
            $out['S'] = 1;
        }
    }

    return implode('', array_keys($out));
}

function timeit2(callable $fn): array
{
    for ($i = 0; $i < 2; $i++) {
        $fn();
    }
    $t = [];
    for ($i = 0; $i < 5; $i++) {
        $s = hrtime(true);
        $fn();
        $t[] = (hrtime(true) - $s) / 1e6;
    }
    sort($t);

    return ['ms' => round($t[2], 2), 'max' => round($t[4], 2)];
}

$results = ['n' => $n, 'date' => date('c'), 'rows' => []];
$add = static function (string $group, string $name, callable $fn, string $sql) use (&$results, $db): void {
    $m = timeit2($fn);
    $row = ['group' => $group, 'name' => $name] + $m + ['plan' => flags2($db, $sql)];
    $results['rows'][] = $row;
    printf("%-8s %-70s %8.2f мс  %s\n", $group, $name, $row['ms'], $row['plan']);
};

// Оценка плотности: доля товаров каталога, имеющих значение (из «базового состояния» — в приложении оно берётся из предрасчёта).
$total = max(1, (int) $db->setQuery('SELECT COUNT(*) FROM bn_products')->loadResult());
$dens = [];
foreach ($db->setQuery('SELECT option_id, COUNT(*) c FROM bn_field_values WHERE option_id IS NOT NULL GROUP BY option_id')->loadObjectList() as $r) {
    $dens[(int) $r->option_id] = (int) $r->c / $total;
}
$optDensity = static fn (array $o): float => min(1.0, array_sum(array_map(static fn (int $x): float => $dens[$x] ?? 0.0, $o)));

// Сценарии: [поле, значения]; плотность считается как произведение (независимость).
$S = [
    'бренд частый (≈18%)' => [['opt', 1, [1]]],
    'бренд средний' => [['opt', 1, [3, 4]]],
    'материал (≈8%)' => [['opt', 2, [31]]],
    'материал×страна (≈0,4%)' => [['opt', 2, [31]], ['opt', 3, [43]]],
    'бренд×материал×цвет' => [['opt', 1, [3, 4]], ['opt', 2, [33, 36]], ['opt', 8, [130, 131]]],
    'частый×материал×страна' => [['opt', 1, [1]], ['opt', 2, [31]], ['opt', 3, [43, 44]]],
    'редкий: цвет×размер×тег' => [['opt', 8, [130, 131]], ['opt', 9, [141, 142]], ['opt', 7, [91]]],
    'цена широкая' => [['price', 5000, 95000]],
    'цена узкая + бренд' => [['price', 20000, 25000], ['opt', 1, [3, 4]]],
];
$density = static function (array $filters) use ($optDensity): float {
    $d = 1.0;
    foreach ($filters as $f) {
        $d *= $f[0] === 'opt' ? $optDensity($f[2]) : ($f[0] === 'price' ? min(1.0, ($f[2] - $f[1]) / 100000) : 0.1);
    }

    return $d;
};

$exists = static function (array $f, int $k): string {
    return match ($f[0]) {
        'opt' => "EXISTS (SELECT 1 FROM bn_field_values v$k WHERE v$k.product_id = pc.product_id AND v$k.field_id = {$f[1]} AND v$k.option_id IN (" . implode(',', $f[2]) . '))',
        'price' => "pc.price_sort <= {$f[2]} AND p.price_max >= {$f[1]}",
    };
};
$lead = static function (array $filters) use ($optDensity): ?int {
    $best = null;
    $bd = 2.0;
    foreach ($filters as $k => $f) {
        if ($f[0] === 'opt') {
            $d = $optDensity($f[2]);
            if ($d < $bd) {
                $bd = $d;
                $best = $k;
            }
        }
    }

    return $best;
};
$orders = ['order' => ['pc.sort_order, pc.product_id', 'idx_l_order'], 'price' => ['pc.price_sort, pc.product_id', 'idx_l_price']];

/** A: базовая форма — EXISTS, оптимизатор выбирает сам. */
$fA = static function (int $cat, array $filters, string $ord) use ($exists): string {
    $w = [];
    foreach ($filters as $k => $f) {
        $w[] = $exists($f, $k);
    }

    return "SELECT pc.product_id FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND " . implode(' AND ', $w) . " ORDER BY {$GLOBALS['orders'][$ord][0]} LIMIT 24";
};
/** F: идём по индексу сортировки, останавливаемся на 24-м совпадении (для плотных условий). */
$fF = static function (int $cat, array $filters, string $ord) use ($exists): string {
    $w = [];
    foreach ($filters as $k => $f) {
        $w[] = $exists($f, $k);
    }
    $idx = $GLOBALS['orders'][$ord][1];

    return "SELECT pc.product_id FROM bn_product_categories pc FORCE INDEX ($idx) JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND " . implode(' AND ', $w) . " ORDER BY {$GLOBALS['orders'][$ord][0]} LIMIT 24";
};
/** D: ведущая выборка по самому редкому значению, затем витрина по первичному ключу и сортировка малой выборки. */
$fD = static function (int $cat, array $filters, string $ord) use ($exists, $lead, $fA): string {
    $l = $lead($filters);
    if ($l === null) {
        return $fA($cat, $filters, $ord);
    }
    $w = [];
    foreach ($filters as $k => $f) {
        if ($k !== $l) {
            $w[] = $exists($f, $k);
        }
    }

    return 'SELECT STRAIGHT_JOIN pc.product_id FROM (SELECT DISTINCT product_id FROM bn_field_values WHERE field_id = ' . $filters[$l][1] . ' AND option_id IN (' . implode(',', $filters[$l][2]) . ")) lead_ids JOIN bn_product_categories pc ON pc.catid = $cat AND pc.product_id = lead_ids.product_id AND pc.state = 1 JOIN bn_products p ON p.id = pc.product_id AND p.state = 1"
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY {$GLOBALS['orders'][$ord][0]} LIMIT 24";
};
$GLOBALS['orders'] = $orders;
/** H: выбор по плотности (порог — параметр): плотное → F, редкое → D. */
$fH = static function (int $cat, array $filters, string $ord, float $theta) use ($density, $fF, $fD): string {
    return $density($filters) >= $theta ? $fF($cat, $filters, $ord) : $fD($cat, $filters, $ord);
};

$cats = ['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12];
echo "== Формы: A (EXISTS), F (индекс сортировки, ранняя остановка), D (ведущая выборка) ==\n";
foreach ($S as $sname => $filters) {
    printf("-- %s: плотность %.4f\n", $sname, $density($filters));
    $results['density'][$sname] = round($density($filters), 5);
    foreach ($cats as $clabel => $cat) {
        foreach (['order', 'price'] as $oname) {
            if ($oname === 'price' && $clabel !== 'root(1)') {
                continue;
            }
            foreach (['A' => $fA, 'F' => $fF, 'D' => $fD] as $fname => $form) {
                $sql = $form($cat, $filters, $oname);
                $add('forms', "$sname / $clabel / $oname / $fname", static function () use ($db, $sql) {
                    $db->setQuery($sql)->loadColumn();
                }, $sql);
            }
        }
    }
}

// Корректность: форма F и D дают тот же состав страницы, что A.
$bad = [];
foreach ($S as $sname => $filters) {
    foreach (['order', 'price'] as $oname) {
        $ref = $db->setQuery($fA(1, $filters, $oname))->loadColumn();
        foreach (['F' => $fF, 'D' => $fD] as $fname => $form) {
            if ($db->setQuery($form(1, $filters, $oname))->loadColumn() !== $ref) {
                $bad[] = "$sname/$oname/$fname";
            }
        }
    }
}
$results['consistency'] = $bad ?: 'ok';
echo 'Согласованность: ' . (is_array($bad) && $bad ? implode('; ', $bad) : 'ok') . "\n";

// ===== Число найденных: через ведущую выборку (подсчёт с потолком) ================================================================================
echo "== Число найденных с потолком 10001: A (EXISTS по витрине) и D (ведущая выборка) ==\n";
foreach (['бренд частый (≈18%)', 'материал×страна (≈0,4%)', 'бренд×материал×цвет', 'редкий: цвет×размер×тег'] as $sname) {
    foreach (['A' => $fA, 'D' => $fD] as $fname => $form) {
        $inner = preg_replace('/^SELECT (STRAIGHT_JOIN )?pc\.product_id/', 'SELECT $1 1', preg_replace('/ ORDER BY .* LIMIT 24$/', ' LIMIT 10001', $form(1, $S[$sname], 'order')));
        $sql = "SELECT COUNT(*) FROM ($inner) t";
        $add('count', "$sname / root / $fname", static function () use ($db, $sql) {
            $db->setQuery($sql)->loadResult();
        }, $sql);
    }
}

// ===== Индекс: размер и влияние =============================================================================================================================
echo "== Покрывающий индекс (product_id, field_id, option_id, num): размер и заменяемость idx_product ==\n";
$st = $db->setQuery("SHOW TABLE STATUS LIKE 'bn_field_values'")->loadAssoc();
$results['table_before_mb'] = ['data' => round((int) $st['Data_length'] / 1048576, 1), 'index' => round((int) $st['Index_length'] / 1048576, 1)];
q2($db, 'ALTER TABLE bn_field_values ADD KEY idx_cov (product_id, field_id, option_id, num)');
q2($db, 'ANALYZE TABLE bn_field_values');
$st = $db->setQuery("SHOW TABLE STATUS LIKE 'bn_field_values'")->loadAssoc();
$results['table_after_mb'] = ['data' => round((int) $st['Data_length'] / 1048576, 1), 'index' => round((int) $st['Index_length'] / 1048576, 1)];
printf("field_values: индексы %.1f МБ → %.1f МБ\n", $results['table_before_mb']['index'], $results['table_after_mb']['index']);
echo "-- с idx_cov --\n";
foreach (['бренд×материал×цвет', 'частый×материал×страна', 'редкий: цвет×размер×тег', 'материал×страна (≈0,4%)'] as $sname) {
    foreach (['root(1)' => 1, 'L2(12)' => 12] as $clabel => $cat) {
        foreach (['A' => $fA, 'F' => $fF, 'D' => $fD] as $fname => $form) {
            $sql = $form($cat, $S[$sname], 'order');
            $add('cov', "$sname / $clabel / order / $fname (idx_cov)", static function () use ($db, $sql) {
                $db->setQuery($sql)->loadColumn();
            }, $sql);
        }
    }
}
// Заменяет ли idx_cov старый idx_product (префикс совпадает): без него те же замеры.
q2($db, 'ALTER TABLE bn_field_values DROP KEY idx_product');
q2($db, 'ANALYZE TABLE bn_field_values');
echo "-- idx_product удалён, остался idx_cov --\n";
foreach (['бренд×материал×цвет', 'редкий: цвет×размер×тег'] as $sname) {
    foreach (['A' => $fA, 'D' => $fD] as $fname => $form) {
        $sql = $form(1, $S[$sname], 'order');
        $add('cov-only', "$sname / root(1) / order / $fname (только idx_cov)", static function () use ($db, $sql) {
            $db->setQuery($sql)->loadColumn();
        }, $sql);
    }
}
$st = $db->setQuery("SHOW TABLE STATUS LIKE 'bn_field_values'")->loadAssoc();
$results['table_cov_only_mb'] = ['data' => round((int) $st['Data_length'] / 1048576, 1), 'index' => round((int) $st['Index_length'] / 1048576, 1)];

// Возвращаем схему проекта.
q2($db, 'ALTER TABLE bn_field_values ADD KEY idx_product (product_id, field_id)');
q2($db, 'ALTER TABLE bn_field_values DROP KEY idx_cov');
q2($db, 'ANALYZE TABLE bn_field_values');

@mkdir(__DIR__ . '/results');
file_put_contents(__DIR__ . "/results/filter2-$n.json", json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nГотово: dev/bench/results/filter2-$n.json\n";
