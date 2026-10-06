<?php
// Dev-only: генератор тестового каталога и замеры скорости схемы Dharma Shop.
//
//   php dev/bench/bench.php generate <N>     создать таблицы bn_* (копия схемы) и заполнить N товарами
//   php dev/bench/bench.php run <N>          замерить запросы (результат в dev/bench/results/<N>.json)
//   php dev/bench/bench.php all <N>          generate + run
//   php dev/bench/bench.php drop             удалить все таблицы bn_*
//
// Данные лежат в таблицах с префиксом bn_ той же базы тестового сайта (DHARMA_SITE), настоящие таблицы не затрагиваются.
// Дерево категорий: 1 корень → 10 → 100 → 1000 листьев (id 1; 2–11; 12–111; 112–1111).
require __DIR__ . '/../boot.php';

use Joomla\Database\DatabaseDriver;

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$cmd = $argv[1] ?? '';
$n = (int) ($argv[2] ?? 0);
$root = dirname(__DIR__, 2);

function run(DatabaseDriver $db, string $sql): void
{
    $db->setQuery($sql)->execute();
}

function benchTables(DatabaseDriver $db): array
{
    return array_values(array_filter($db->getTableList(), static fn ($t) => str_starts_with($t, 'bn_')));
}

function dropAll(DatabaseDriver $db): void
{
    foreach (benchTables($db) as $t) {
        run($db, 'DROP TABLE IF EXISTS `' . $t . '`');
    }
}

function r(string $salt, string $i, int $mod): string
{
    return "MOD(CRC32(CONCAT('$salt', $i)), $mod)";
}

if ($cmd === 'drop') {
    dropAll($db);
    exit("dropped\n");
}

if ($cmd === 'generate' || $cmd === 'all') {
    $t0 = microtime(true);
    dropAll($db);

    // Схема — тот же install.mysql.utf8.sql с другим префиксом таблиц.
    $schema = str_replace('#__dharmashop_', 'bn_', file_get_contents($root . '/com_dharmashop/admin/sql/install.mysql.utf8.sql'));
    foreach (DatabaseDriver::splitSql($schema) as $stmt) {
        if (trim($stmt) !== '') {
            run($db, $stmt);
        }
    }

    // Последовательность 1..N.
    run($db, 'CREATE TABLE bn_seq (i int unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    run($db, 'SET SESSION cte_max_recursion_depth = 2000000');
    run($db, "INSERT INTO bn_seq WITH RECURSIVE s(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM s WHERE i < $n) SELECT i FROM s");

    // Поля: [id, alias, type, store, level, число значений / диапазон, filterable]
    $fields = [
        [1, 'brand', 'list', 'option', 1, 30], [2, 'material', 'list', 'option', 1, 12],
        [3, 'country', 'list', 'option', 1, 20], [4, 'style', 'list', 'option', 1, 6],
        [5, 'season', 'list', 'option', 1, 4], [6, 'collection', 'list', 'option', 1, 15],
        [7, 'tags', 'multilist', 'option', 1, 40], [8, 'color', 'list', 'option', 2, 12],
        [9, 'size', 'list', 'option', 2, 8],
        [10, 'weight', 'number', 'number', 1, 50], [11, 'width', 'number', 'number', 1, 200],
        [12, 'height', 'number', 'number', 1, 200], [13, 'power', 'number', 'number', 1, 2000],
        [14, 'model', 'string', 'string', 1, 0], [15, 'barcode', 'string', 'string', 1, 0],
        [16, 'series', 'string', 'string', 1, 0], [17, 'warranty', 'string', 'string', 1, 0],
        [18, 'notes', 'text', 'text', 1, 0], [19, 'delivery_info', 'text', 'text', 1, 0],
    ];
    $optOffset = [];
    $next = 1;
    foreach ($fields as [$id, $alias, $type, $store, $level, $count]) {
        run($db, "INSERT INTO bn_fields (id, alias, title, type, store, level, filterable) VALUES ($id, '$alias', '$alias', '$type', '$store', $level, 1)");
        if ($store === 'option') {
            $optOffset[$id] = $next;
            for ($k = 0; $k < $count; $k++) {
                run($db, 'INSERT INTO bn_field_options (id, field_id, alias, title) VALUES (' . ($next + $k) . ", $id, 'o" . ($k + 1) . "', 'Значение " . ($k + 1) . "')");
            }
            $next += $count;
        }
    }

    // Товары.
    $adj = "ELT(1 + " . r('a', 'i', 10) . ", 'Красный','Синий','Большой','Малый','Классический','Современный','Лёгкий','Прочный','Складной','Универсальный')";
    $noun = "ELT(1 + " . r('n', 'i', 10) . ", 'диван','стол','стул','шкаф','кровать','комод','кресло','тумба','полка','стеллаж')";
    run($db, "INSERT INTO bn_products (id, catid, title, alias, introtext, `fulltext`, state, featured, variants_mode, unit, created, created_by)
        SELECT i,
          112 + LEAST(999, FLOOR(POW(" . r('c', 'i', 1000000) . " / 1000000, 1.8) * 1000)),
          CONCAT($adj, ' ', $noun, ' ', i), CONCAT('product-', i),
          REPEAT('Краткое описание товара. ', 8), REPEAT('Полное описание товара с подробностями. ', 25),
          IF(" . r('s', 'i', 100) . " < 95, 1, 0), IF(" . r('f', 'i', 50) . " = 0, 1, 0),
          IF(" . r('m', 'i', 100) . " < 40, 2, 1), 'шт',
          DATE_ADD('2023-10-01', INTERVAL " . r('d', 'i', 1095 * 86400) . " SECOND), 1
        FROM bn_seq");

    // Варианты: простой товар — 1, с вариантами — от 2 до 6.
    $ks = 'SELECT 1 k UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6';
    run($db, "INSERT INTO bn_variants (product_id, sku, state, is_default, ordering, stock_tracked, backorder, stock_total, in_stock, created)
        SELECT pid, CONCAT('SKU-', pid, '-', k), 1, IF(k = 1, 1, 0), k, t, 0, q, IF(t = 0 OR q > 0, 1, 0), cr
        FROM (
          SELECT p.id pid, k.k k, p.created cr,
                 MOD(CRC32(CONCAT('t', p.id, '-', k.k)), 2) t, MOD(CRC32(CONCAT('q', p.id, '-', k.k)), 20) q
          FROM bn_products p JOIN ($ks) k ON k.k <= IF(p.variants_mode = 2, 2 + MOD(CRC32(CONCAT('v', p.id)), 5), 1)
        ) x");

    // Цены: у 10 % скидка, у 1 % «по запросу».
    run($db, "INSERT INTO bn_variant_prices (variant_id, price_list_id, price, sale_price, price_final)
        SELECT id, 1, price, sale, COALESCE(sale, price) FROM (
          SELECT v.id,
                 IF(MOD(CRC32(CONCAT('r', v.id)), 100) = 0, NULL, ROUND(100 + MOD(CRC32(CONCAT('p', v.id)), 99900) + MOD(CRC32(CONCAT('pp', v.id)), 100) / 100, 2)) price,
                 IF(MOD(CRC32(CONCAT('z', v.id)), 10) = 0, ROUND((100 + MOD(CRC32(CONCAT('p', v.id)), 99900)) * 0.8, 2), NULL) sale
          FROM bn_variants v
        ) y");
    run($db, "INSERT INTO bn_stock_levels (variant_id, location_id, qty, reserved) SELECT id, 1, stock_total, 0 FROM bn_variants WHERE stock_tracked = 1");

    // Производные данные товара.
    run($db, "UPDATE bn_products p JOIN (
          SELECT v.product_id pid, MIN(vp.price_final) mn, MAX(vp.price_final) mx, MAX(v.in_stock) ins, COUNT(*) c
          FROM bn_variants v JOIN bn_variant_prices vp ON vp.variant_id = v.id AND vp.price_list_id = 1 GROUP BY v.product_id
        ) a ON a.pid = p.id
        SET p.price_min = a.mn, p.price_max = a.mx, p.in_stock = a.ins, p.variants_count = a.c");

    // Витринный индекс: лист, родитель, дед, корень + 20 % товаров во второй категории.
    $pc = static function (string $catExpr, int $direct, int $main, string $where = '1=1', string $ignore = ''): string {
        return "INSERT $ignore INTO bn_product_categories (catid, product_id, direct, is_main, state, sort_order, price_sort, in_stock, featured, created, title_sort)
            SELECT $catExpr, p.id, $direct, $main, p.state, MOD(CRC32(CONCAT('o', p.id, '-', $catExpr)), 1000000), p.price_min, p.in_stock, p.featured, p.created, LEFT(LOWER(p.title), 60)
            FROM bn_products p WHERE $where";
    };
    run($db, $pc('p.catid', 1, 1));
    run($db, $pc('(12 + FLOOR((p.catid - 112) / 10))', 0, 0));
    run($db, $pc('(2 + FLOOR((p.catid - 112) / 100))', 0, 0));
    run($db, $pc('1', 0, 0));
    $extra = '(112 + MOD(CRC32(CONCAT(\'x\', p.id)), 1000))';
    $has = "MOD(CRC32(CONCAT('y', p.id)), 5) = 0 AND $extra <> p.catid";
    run($db, $pc($extra, 1, 0, $has, 'IGNORE'));
    run($db, $pc("(12 + FLOOR(($extra - 112) / 10))", 0, 0, $has, 'IGNORE'));
    run($db, $pc("(2 + FLOOR(($extra - 112) / 100))", 0, 0, $has, 'IGNORE'));

    // Значения полей.
    $ins = 'INSERT INTO bn_field_values (product_id, variant_id, field_id, option_id, num, str)';
    foreach ($fields as [$id, $alias, $type, $store, $level, $count]) {
        if ($level !== 1) {
            continue;
        }
        if ($store === 'option' && $type === 'list') {
            $expr = $id === 1
                ? "FLOOR(POW(" . r("f$id", 'i', 1000000) . " / 1000000, 2) * $count)"
                : r("f$id", 'i', $count);
            run($db, "$ins SELECT i, NULL, $id, " . $optOffset[$id] . " + $expr, NULL, NULL FROM bn_seq");
        } elseif ($type === 'multilist') {
            for ($k = 0; $k < 3; $k++) {
                run($db, "$ins SELECT i, NULL, $id, " . $optOffset[$id] . " + MOD(" . r("f$id", 'i', $count) . " + " . ($k * 13) . ", $count), NULL, NULL FROM bn_seq");
            }
        } elseif ($store === 'number') {
            run($db, "$ins SELECT i, NULL, $id, NULL, ROUND(1 + " . r("f$id", 'i', $count * 100) . " / 100, 2), NULL FROM bn_seq");
        } elseif ($store === 'string') {
            run($db, "$ins SELECT i, NULL, $id, NULL, NULL, CONCAT('M', " . r("f$id", 'i', 5000) . ") FROM bn_seq WHERE " . r("h$id", 'i', 2) . " = 0");
        } elseif ($store === 'text') {
            run($db, "INSERT INTO bn_field_texts (product_id, variant_id, field_id, value) SELECT i, NULL, $id, REPEAT('Текст поля. ', 20) FROM bn_seq");
        }
    }
    foreach ([8 => 5, 9 => 3] as $fid => $step) {
        $cnt = $fid === 8 ? 12 : 8;
        run($db, "$ins SELECT v.product_id, v.id, $fid, " . $optOffset[$fid] . " + MOD(" . r("c$fid", 'v.product_id', $cnt) . " + (v.ordering - 1) * $step, $cnt), NULL, NULL FROM bn_variants v");
    }

    // Поиск.
    run($db, "INSERT INTO bn_search (product_id, title_norm, body) SELECT id, LOWER(title), CONCAT(LOWER(title), ' ', LEFT(introtext, 200)) FROM bn_products");

    foreach (benchTables($db) as $t) {
        run($db, "ANALYZE TABLE `$t`");
    }
    printf("generated %d products in %.1f s\n", $n, microtime(true) - $t0);
}

if ($cmd === 'run' || $cmd === 'all') {
    require __DIR__ . '/queries.php';
}

if (!in_array($cmd, ['generate', 'run', 'all', 'drop'], true)) {
    fwrite(STDERR, "usage: php dev/bench/bench.php generate|run|all <N> | drop\n");
    exit(1);
}
