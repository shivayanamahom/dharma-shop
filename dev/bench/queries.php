<?php
// Dev-only: набор замеров для dev/bench/bench.php (подключается командами run и all).
// Ожидает переменные $db (DatabaseDriver), $n (число товаров), $root (корень репозитория).

$brand = [3, 4];          // option_id поля «brand» (30 значений, id 1–30)
$material = [33, 36];     // «material» (12 значений, id 31–42)
$color = [130, 131];      // «color», поле варианта (12 значений, id 128–139)

$orders = [
    'order' => 'pc.sort_order, pc.product_id',
    'price_asc' => 'pc.price_sort, pc.product_id',
    'price_desc' => 'pc.price_sort DESC, pc.product_id DESC',
    'new' => 'pc.created DESC, pc.product_id DESC',
    'title' => 'pc.title_sort, pc.product_id',
];

$cats = ['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12, 'leaf-hot(112)' => 112, 'leaf-mid(412)' => 412];

/** Шаг 1: ключи страницы по индексу витрины. */
function listingIdsSql(int $cat, string $order, int $limit, int $offset, string $extra = ''): string
{
    return "SELECT pc.product_id FROM bn_product_categories pc WHERE pc.catid = $cat AND pc.state = 1 $extra ORDER BY $order LIMIT $limit OFFSET $offset";
}

/** Шаг 2: карточки страницы по первичному ключу. */
function cardsSql(array $ids): string
{
    return 'SELECT id, title, alias, catid, price_min, price_max, in_stock, cover, introtext FROM bn_products WHERE id IN (' . implode(',', $ids ?: [0]) . ')';
}

function filterSql(array $brand, array $material, array $color): string
{
    $ex = fn (int $f, array $o) => "AND EXISTS (SELECT 1 FROM bn_field_values v WHERE v.product_id = pc.product_id AND v.field_id = $f AND v.option_id IN (" . implode(',', $o) . '))';

    return 'AND pc.price_sort BETWEEN 5000 AND 40000 ' . $ex(1, $brand) . ' ' . $ex(2, $material) . ' ' . $ex(8, $color);
}

function explainFlags(\Joomla\Database\DatabaseDriver $db, string $sql): array
{
    $rows = $db->setQuery('EXPLAIN ' . $sql)->loadAssocList();
    $flags = [];
    foreach ($rows as $r) {
        $extra = (string) ($r['Extra'] ?? '');
        if (stripos($extra, 'filesort') !== false) {
            $flags['filesort'] = true;
        }
        if (stripos($extra, 'temporary') !== false) {
            $flags['temporary'] = true;
        }
        if (($r['type'] ?? '') === 'ALL' && !str_starts_with((string) ($r['table'] ?? ''), '<derived')) {
            $flags['fullscan'] = $r['table'] ?? true;
        }
    }

    return $flags;
}

/** Замер: 2 прогрева + 9 прогонов, медиана и максимум в мс. */
function measure(callable $fn): array
{
    for ($i = 0; $i < 2; $i++) {
        $fn();
    }
    $t = [];
    for ($i = 0; $i < 9; $i++) {
        $s = hrtime(true);
        $fn();
        $t[] = (hrtime(true) - $s) / 1e6;
    }
    sort($t);

    return ['ms' => round($t[4], 2), 'max' => round($t[8], 2)];
}

$results = [];
$add = function (string $name, callable $fn, ?string $explain = null) use (&$results, $db): void {
    $m = measure($fn);
    $m['name'] = $name;
    $m['plan'] = $explain ? explainFlags($db, $explain) : [];
    $results[] = $m;
    printf("%-58s %8.2f мс (max %.2f) %s\n", $name, $m['ms'], $m['max'], $m['plan'] ? json_encode($m['plan']) : '');
};

// 1. Листинг категории, 24 позиции, все сортировки.
foreach ($cats as $label => $cat) {
    foreach ($orders as $oname => $order) {
        $sql = listingIdsSql($cat, $order, 24, 0);
        $add("листинг $label / $oname", function () use ($db, $sql) {
            $ids = $db->setQuery($sql)->loadColumn();
            $db->setQuery(cardsSql($ids))->loadAssocList();
        }, $sql);
    }
}

// 2. Глубокая страница (смещение 2400 = страница 101) для корня.
foreach (['order', 'price_asc', 'new'] as $oname) {
    $sql = listingIdsSql(1, $orders[$oname], 24, 2400);
    $add("листинг root(1) / $oname / смещение 2400", function () use ($db, $sql) {
        $ids = $db->setQuery($sql)->loadColumn();
        $db->setQuery(cardsSql($ids))->loadAssocList();
    }, $sql);
}

// 3. Листинг + диапазон цены + 3 фильтра по полям (бренд, материал, цвет варианта).
$filter = filterSql($brand, $material, $color);
foreach (['root(1)' => 1, 'L1(2)' => 2, 'L2(12)' => 12] as $label => $cat) {
    foreach (['order', 'price_asc', 'new'] as $oname) {
        $sql = listingIdsSql($cat, $orders[$oname], 24, 0, $filter);
        $add("листинг+фильтр $label / $oname", function () use ($db, $sql) {
            $ids = $db->setQuery($sql)->loadColumn();
            $db->setQuery(cardsSql($ids))->loadAssocList();
        }, $sql);
    }
}

// 4. Число товаров: с потолком и точное.
foreach (['root(1)' => 1, 'L1(2)' => 2] as $label => $cat) {
    $capped = "SELECT COUNT(*) FROM (SELECT 1 FROM bn_product_categories WHERE catid = $cat AND state = 1 LIMIT 10001) t";
    $add("счётчик с потолком $label", fn () => $db->setQuery($capped)->loadResult(), $capped);
    $exact = "SELECT COUNT(*) FROM bn_product_categories WHERE catid = $cat AND state = 1";
    $add("счётчик точный $label", fn () => $db->setQuery($exact)->loadResult(), $exact);
}
$countFilter = 'SELECT COUNT(*) FROM (SELECT 1 FROM bn_product_categories pc WHERE pc.catid = 1 AND pc.state = 1 ' . $filter . ' LIMIT 10001) t';
$add('счётчик с фильтром и потолком root(1)', fn () => $db->setQuery($countFilter)->loadResult(), $countFilter);

// 4b. Счётчики значений фильтра («бренд (число товаров)») в категории: без фильтра и при выбранном цвете.
foreach (['root(1)' => 1, 'L1(2)' => 2] as $label => $cat) {
    $facet = "SELECT v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 1 WHERE pc.catid = $cat AND pc.state = 1 GROUP BY v.option_id";
    $add("счётчики бренда в $label, без фильтра", fn () => $db->setQuery($facet)->loadAssocList(), $facet);
    $facetF = "SELECT v.option_id, COUNT(*) c FROM bn_product_categories pc JOIN bn_field_values v ON v.product_id = pc.product_id AND v.field_id = 1 WHERE pc.catid = $cat AND pc.state = 1 AND EXISTS (SELECT 1 FROM bn_field_values w WHERE w.product_id = pc.product_id AND w.field_id = 8 AND w.option_id IN (" . implode(',', $color) . ")) GROUP BY v.option_id";
    $add("счётчики бренда в $label, выбран цвет", fn () => $db->setQuery($facetF)->loadAssocList(), $facetF);
}

// 5. Карточка товара: товар + варианты + цены + значения полей + тексты (20 товаров, среднее).
$ids = [];
for ($k = 1; $k <= 20; $k++) {
    $ids[] = max(1, (int) round($k * $n / 21));
}
$cardRows = $db->setQuery('SELECT id, catid, alias FROM bn_products WHERE id IN (' . implode(',', $ids) . ')')->loadAssocList();
$m = measure(function () use ($db, $cardRows) {
    foreach ($cardRows as $c) {
        $p = $db->setQuery('SELECT * FROM bn_products WHERE catid = ' . (int) $c['catid'] . ' AND alias = ' . $db->quote($c['alias']))->loadAssoc();
        $vs = $db->setQuery('SELECT * FROM bn_variants WHERE product_id = ' . (int) $p['id'] . ' AND state = 1 ORDER BY ordering')->loadAssocList();
        $vids = array_column($vs, 'id');
        $db->setQuery('SELECT * FROM bn_variant_prices WHERE price_list_id = 1 AND variant_id IN (' . implode(',', $vids) . ')')->loadAssocList();
        $db->setQuery('SELECT variant_id, field_id, option_id, num, str FROM bn_field_values WHERE product_id = ' . (int) $p['id'])->loadAssocList();
        $db->setQuery('SELECT field_id, value FROM bn_field_texts WHERE product_id = ' . (int) $p['id'])->loadAssocList();
    }
});
$m['ms'] = round($m['ms'] / count($cardRows), 2);
$m['max'] = round($m['max'] / count($cardRows), 2);
$m['name'] = 'карточка товара (5 запросов, среднее на товар)';
$m['plan'] = [];
$results[] = $m;
printf("%-58s %8.2f мс (max %.2f)\n", $m['name'], $m['ms'], $m['max']);

// 6. Поиск.
$ft = "SELECT product_id FROM bn_search WHERE MATCH(title_norm, body) AGAINST ('+красный +диван*' IN BOOLEAN MODE) LIMIT 24";
$add('поиск FULLTEXT «красный диван»', fn () => $db->setQuery($ft)->loadColumn(), $ft);
$ft1 = "SELECT product_id FROM bn_search WHERE MATCH(title_norm, body) AGAINST ('+стол*' IN BOOLEAN MODE) LIMIT 24";
$add('поиск FULLTEXT «стол» (частое слово)', fn () => $db->setQuery($ft1)->loadColumn(), $ft1);
$sku = "SELECT DISTINCT product_id FROM bn_variants WHERE sku LIKE 'SKU-" . max(1, intdiv($n, 3)) . "-%' LIMIT 24";
$add('поиск по артикулу (префикс)', fn () => $db->setQuery($sku)->loadColumn(), $sku);

// 7. Админский список (штатный: сортировка по id, фильтр по состоянию, постраничность).
$a1 = 'SELECT id, title, state, catid, created FROM bn_products WHERE state IN (0, 1) ORDER BY id DESC LIMIT 20 OFFSET 0';
$add('админка: список, страница 1', fn () => $db->setQuery($a1)->loadAssocList(), $a1);
$a2 = 'SELECT id, title, state, catid, created FROM bn_products WHERE state IN (0, 1) ORDER BY id DESC LIMIT 20 OFFSET ' . max(0, $n - 1000);
$add('админка: список, глубокая страница', fn () => $db->setQuery($a2)->loadAssocList(), $a2);
$a3 = 'SELECT id, title, state, catid, created FROM bn_products WHERE catid = 112 AND state IN (0, 1) ORDER BY id DESC LIMIT 20';
$add('админка: фильтр по основной категории', fn () => $db->setQuery($a3)->loadAssocList(), $a3);
$a4 = "SELECT id, title FROM bn_products WHERE title LIKE '%диван 12%' ORDER BY id DESC LIMIT 20";
$add('админка: поиск LIKE %…% по названию (известное слабое место)', fn () => $db->setQuery($a4)->loadAssocList(), $a4);

// Размеры.
$sizes = $db->setQuery("SELECT table_name AS t, ROUND((data_length + index_length) / 1048576, 1) AS mb, table_rows AS r FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'bn\\_%' AND table_name <> 'bn_seq' ORDER BY (data_length + index_length) DESC")->loadAssocList();

@mkdir(__DIR__ . '/results', 0777, true);
file_put_contents(
    __DIR__ . "/results/$n.json",
    json_encode(['n' => $n, 'date' => date('c'), 'mysql' => $db->getVersion(), 'results' => $results, 'sizes' => $sizes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "\nРазмеры таблиц (МБ):\n";
foreach ($sizes as $s) {
    printf("  %-30s %8s МБ  ~%s строк\n", $s['t'], $s['mb'], $s['r']);
}
