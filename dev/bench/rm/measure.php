<?php
// Dev-only: замеры страниц RadicalMart по HTTP как «чёрного ящика» + время и число запросов к базе из performance_schema.
//   DHARMA_SITE=/Volumes/Work/Server/www/dharmatest DHARMA_URL=https://dharmatest.test php dev/bench/rm/measure.php
// Результат: dev/bench/rm/results/<число товаров>.json. Исходного кода RadicalMart не читаем: только ответы страниц и статистика БД.
require __DIR__ . '/../../boot.php';

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$base = rtrim(getenv('DHARMA_URL') ?: 'https://dharmatest.test', '/');
$schema = $db->setQuery('SELECT DATABASE()')->loadResult();
$total = (int) $db->setQuery('SELECT COUNT(*) FROM #__radicalmart_products')->loadResult();
$orig = $db->setQuery('SELECT @@slow_query_log_file, @@long_query_time')->loadRow();
$db->setQuery('SET GLOBAL long_query_time = 0')->execute();
register_shutdown_function(function () use ($db, $orig) {
    $db->setQuery('SET GLOBAL slow_query_log = 0')->execute();
    $db->setQuery('SET GLOBAL slow_query_log_file = ' . $db->quote($orig[0]))->execute();
    $db->setQuery('SET GLOBAL long_query_time = ' . (float) $orig[1])->execute();
});
$items = (int) $db->setQuery('SELECT COUNT(*) FROM #__radicalmart_categories_items')->loadResult();
echo "товаров RM: $total, строк в индексе списков: $items\n";

$deep = (int) (floor($total / 2 / 23) * 23);
$clone = $db->setQuery("SELECT CONCAT('/', c.path, '/', p.alias) FROM #__radicalmart_products p JOIN #__radicalmart_categories c ON c.id = p.category WHERE c.path LIKE 'produktsiya/%' AND p.alias REGEXP '-bn[0-9]*5$' ORDER BY p.id LIMIT 1 OFFSET " . max(0, intdiv($total, 20)) . "")->loadResult();

// [название, путь, cookie]
$cases = [
    ['не-RM страница (статья), для вычитания базовой стоимости Joomla', '/o-kompanii/o-nas', ''],
    ['главная', '/', ''],
    ['категория «Продукция» (корень), стр. 1', '/produktsiya', ''],
    ['категория «Продукция», стр. 2', '/produktsiya?start=23', ''],
    ["категория «Продукция», глубокая стр. (start=$deep)", "/produktsiya?start=$deep", ''],
    ['категория-узел «Горячие смеси»', '/produktsiya/goryachie-asfaltobetonnye-smesi', ''],
    ['категория-лист «Щебень гранитный»', '/produktsiya/inertnye-materialy/sheben-granitnyy', ''],
    ['корень, сортировка по цене', '/produktsiya', 'com_radicalmart_category_list_ordering=ordering_price ASC'],
    ['корень, сортировка по названию', '/produktsiya', 'com_radicalmart_category_list_ordering=ordering_title ASC'],
    ['корень, сортировка по дате (новые)', '/produktsiya', 'com_radicalmart_category_list_ordering=ordering_date DESC'],
    ['корень + фильтр по полю «крупность = 8»', '/produktsiya?filter[fields][krupnost]=8', ''],
    ['карточка исходного товара', '/produktsiya/inertnye-materialy/pgs/pgs-peschano-graviynaya-smes-pgs', ''],
];
if ($clone) {
    $cases[] = ['карточка клонированного товара', $clone, ''];
}

function http(string $url, string $cookie): array
{
    $cmd = 'curl -sk -g -o /dev/null -w "%{http_code} %{time_starttransfer} %{time_total} %{size_download}" ' . ($cookie !== '' ? '-H ' . escapeshellarg('Cookie: ' . $cookie) . ' ' : '') . escapeshellarg($url);
    [$code, $ttfb, $tot, $size] = explode(' ', trim((string) shell_exec($cmd)));

    return ['code' => (int) $code, 'ttfb' => (float) $ttfb * 1000, 'total' => (float) $tot * 1000, 'size' => (int) $size];
}

$results = [];
foreach ($cases as [$name, $path, $cookie]) {
    $url = $base . $path;
    http($url, $cookie);
    http($url, $cookie);
    $t = [];
    for ($i = 0; $i < 9; $i++) {
        $r = http($url, $cookie);
        $t[] = $r['ttfb'];
    }
    sort($t);

    // Профиль БД одного запроса: журнал запросов MySQL на время одного обращения (запросы Joomla идут через prepared statements,
    // поэтому performance_schema их не агрегирует).
    $log = sys_get_temp_dir() . '/rm-bench-slow-' . getmypid() . '.log';
    @unlink($log);
    $db->setQuery('SET GLOBAL slow_query_log = 0')->execute();
    $db->setQuery('SET GLOBAL slow_query_log_file = ' . $db->quote($log))->execute();
    $db->setQuery('SET GLOBAL slow_query_log = 1')->execute();
    $r = http($url, $cookie);
    $db->setQuery('SET GLOBAL slow_query_log = 0')->execute();
    $entries = [];
    foreach (preg_split('/^# Time: /m', (string) @file_get_contents($log)) as $blk) {
        if (!preg_match('/# Query_time: ([\d.]+)\s+Lock_time: [\d.]+\s+Rows_sent: (\d+)\s+Rows_examined: (\d+)/', $blk, $m) || str_contains($blk, 'administrator command')) {
            continue;
        }
        preg_match('/Sort_rows: (\d+).*?Created_tmp_tables: (\d+)/s', $blk, $m2);
        $sql = trim(preg_replace('/^(#.*|SET timestamp=\d+;)\R?/m', '', $blk));
        if ($sql === '' || str_contains($sql, 'slow_query_log') || str_contains($sql, 'performance_schema') || stripos($sql, 'SELECT @@SESSION') === 0 || stripos($sql, 'SET @@SESSION') === 0) {
            continue;
        }
        $entries[] = ['ms' => (float) $m[1] * 1000, 'sent' => (int) $m[2], 'ex' => (int) $m[3], 'sort' => (int) ($m2[1] ?? 0), 'tmp' => (int) ($m2[2] ?? 0), 'sql' => $sql];
    }
    @unlink($log);
    usort($entries, fn ($a, $b) => $b['ms'] <=> $a['ms']);
    $q = count($entries);
    $ms = array_sum(array_column($entries, 'ms'));
    $ex = array_sum(array_column($entries, 'ex'));
    $top = array_map(fn ($x) => ['ms' => round($x['ms'], 2), 'ex' => $x['ex'], 'sent' => $x['sent'], 'sort' => $x['sort'], 'tmp' => $x['tmp'], 'sql' => mb_substr(preg_replace('/\s+/', ' ', $x['sql']), 0, 300)], array_slice($entries, 0, 3));
    $res = ['name' => $name, 'path' => $path, 'http' => $r['code'], 'ttfb_ms' => round($t[4], 1), 'ttfb_max' => round($t[8], 1), 'size' => $r['size'], 'db_queries' => $q, 'db_ms' => round($ms, 1), 'rows_examined' => $ex, 'top' => $top];
    $results[] = $res;
    printf("%-62s http=%d ttfb=%6.1f мс  БД: %3d запр., %7.1f мс, строк просмотрено %d\n", $name, $r['code'], $t[4], $q, $ms, $ex);
}

@mkdir(__DIR__ . '/results', 0777, true);
file_put_contents(__DIR__ . "/results/$total.json", json_encode(['products' => $total, 'items' => $items, 'date' => date('c'), 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
