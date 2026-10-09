<?php
// Dev-only: третий раунд замеров фильтра (F0): слабое место формы F (редкое условие, пустой результат, глубокая страница) и порог переключения.
//
//   php dev/bench/filter3.php run <N>      → dev/bench/results/filter3-<N>.json (таблицы bn_*)
//
// Плотность задаётся шириной диапазона числового поля «power» (field 13, значения 1…2001 равномерно): ширина 200 ≈ 10 %, 20 ≈ 1 %, 2 ≈ 0,1 %, 0,2 ≈ 0,01 %.
require __DIR__ . '/../boot.php';

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$n = (int) ($argv[2] ?? 0);
if (($argv[1] ?? '') !== 'run' || $n < 1) {
    fwrite(STDERR, "usage: php dev/bench/filter3.php run <N>\n");
    exit(1);
}
function flags3(\Joomla\Database\DatabaseDriver $db, string $sql): string
{
    $o = [];
    foreach ($db->setQuery('EXPLAIN ' . $sql)->loadAssocList() as $r) {
        $e = (string) ($r['Extra'] ?? '');
        if (stripos($e, 'filesort') !== false) { $o['F'] = 1; }
        if (stripos($e, 'temporary') !== false) { $o['T'] = 1; }
        if (($r['type'] ?? '') === 'ALL' && !str_starts_with((string) ($r['table'] ?? ''), '<derived')) { $o['S'] = 1; }
    }
    return implode('', array_keys($o));
}
function time3(callable $fn): array
{
    for ($i = 0; $i < 1; $i++) { $fn(); }
    $t = [];
    for ($i = 0; $i < 3; $i++) { $s = hrtime(true); $fn(); $t[] = (hrtime(true) - $s) / 1e6; }
    sort($t);
    return ['ms' => round($t[1], 2), 'max' => round($t[2], 2)];
}
$results = ['n' => $n, 'rows' => []];
$add = static function (string $group, string $name, callable $fn, string $sql) use (&$results, $db): void {
    $m = time3($fn);
    $r = ['group' => $group, 'name' => $name] + $m + ['plan' => flags3($db, $sql)];
    $results['rows'][] = $r;
    printf("%-8s %-72s %9.2f мс %s\n", $group, $name, $r['ms'], $r['plan']);
};
$total = (int) $db->setQuery('SELECT COUNT(*) FROM bn_products')->loadResult();
$exNum = static fn (int $k, float $lo, float $hi): string => "EXISTS (SELECT 1 FROM bn_field_values w$k WHERE w$k.product_id = pc.product_id AND w$k.field_id = 13 AND w$k.num BETWEEN $lo AND $hi)";
$leadNum = static fn (float $lo, float $hi): string => "(SELECT DISTINCT product_id FROM bn_field_values WHERE field_id = 13 AND num BETWEEN $lo AND $hi)";
$fF = static fn (int $cat, string $w, int $off): string => "SELECT pc.product_id FROM bn_product_categories pc FORCE INDEX (idx_l_order) JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND $w ORDER BY pc.sort_order, pc.product_id LIMIT 24 OFFSET $off";
$fA = static fn (int $cat, string $w, int $off): string => "SELECT pc.product_id FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = $cat AND pc.state = 1 AND $w ORDER BY pc.sort_order, pc.product_id LIMIT 24 OFFSET $off";
$fD = static fn (int $cat, string $lead, string $rest, int $off): string => "SELECT STRAIGHT_JOIN pc.product_id FROM $lead lead_ids JOIN bn_product_categories pc ON pc.catid = $cat AND pc.product_id = lead_ids.product_id AND pc.state = 1 JOIN bn_products p ON p.id = pc.product_id AND p.state = 1" . ($rest !== '' ? " WHERE $rest" : '') . " ORDER BY pc.sort_order, pc.product_id LIMIT 24 OFFSET $off";

// Индекс покрывающий — как предлагается в проекте (заменяет idx_product).
$ix = array_column($db->setQuery('SHOW INDEX FROM bn_field_values')->loadAssocList(), 'Key_name');
if (!in_array('idx_cov', $ix, true)) {
    $db->setQuery('ALTER TABLE bn_field_values ADD KEY idx_cov (product_id, field_id, option_id, num)')->execute();
    $db->setQuery('ANALYZE TABLE bn_field_values')->execute();
}
$widths = ['10 % (ширина 200)' => [100, 300], '1 % (ширина 20)' => [100, 120], '0,1 % (ширина 2)' => [100, 102], '0,01 % (ширина 0,2)' => [100, 100.2], 'пусто (вне диапазона)' => [5000, 6000]];
echo "== Одно числовое условие по плотности; корень; страница 1 и страница 51 ==\n";
foreach ($widths as $label => [$lo, $hi]) {
    foreach ([0, 1200] as $off) {
        $page = $off === 0 ? 'стр.1' : 'стр.51';
        foreach (['A' => $fA(1, $exNum(0, $lo, $hi), $off), 'F' => $fF(1, $exNum(0, $lo, $hi), $off), 'D' => $fD(1, $leadNum($lo, $hi), '', $off)] as $f => $sql) {
            $add('density', "$label / root / $page / $f", static function () use ($db, $sql) { $db->setQuery($sql)->loadColumn(); }, $sql);
        }
    }
}
echo "== Два условия: число (плотность) + бренд (3,4), корень, страница 1 ==\n";
$br = "EXISTS (SELECT 1 FROM bn_field_values b WHERE b.product_id = pc.product_id AND b.field_id = 1 AND b.option_id IN (3,4))";
foreach ($widths as $label => [$lo, $hi]) {
    foreach (['A' => $fA(1, $exNum(0, $lo, $hi) . ' AND ' . $br, 0), 'F' => $fF(1, $exNum(0, $lo, $hi) . ' AND ' . $br, 0), 'D' => $fD(1, $leadNum($lo, $hi), $br, 0)] as $f => $sql) {
        $add('two', "$label + бренд / root / $f", static function () use ($db, $sql) { $db->setQuery($sql)->loadColumn(); }, $sql);
    }
}
echo "== Число найденных: потолок 10 001 (F-подобный обход) против ведущей выборки ==\n";
foreach ($widths as $label => [$lo, $hi]) {
    $cF = "SELECT COUNT(*) FROM (SELECT 1 FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = 1 AND pc.state = 1 AND " . $exNum(0, $lo, $hi) . " LIMIT 10001) t";
    $cD = "SELECT COUNT(*) FROM (SELECT 1 FROM " . $leadNum($lo, $hi) . " lead_ids JOIN bn_product_categories pc ON pc.catid = 1 AND pc.product_id = lead_ids.product_id AND pc.state = 1 LIMIT 10001) t";
    $cL = "SELECT COUNT(*) FROM (SELECT 1 FROM bn_product_categories pc JOIN bn_products p ON p.id = pc.product_id AND p.state = 1 WHERE pc.catid = 1 AND pc.state = 1 AND " . $exNum(0, $lo, $hi) . " LIMIT 1001) t";
    foreach (['обход, потолок 10001' => $cF, 'ведущая, потолок 10001' => $cD, 'обход, потолок 1001' => $cL] as $f => $sql) {
        $add('count', "$label / $f", static function () use ($db, $sql) { $db->setQuery($sql)->loadResult(); }, $sql);
    }
}
file_put_contents(__DIR__ . "/results/filter3-$n.json", json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$db->setQuery('ALTER TABLE bn_field_values DROP KEY idx_cov')->execute();
echo "\nГотово\n";
