<?php
// Dev-only: размножает товары RadicalMart на тестовом сайте (DHARMA_SITE должен указывать на сайт с RM, обычно dharmatest).
//   php dev/bench/rm/clone.php <N>     довести число товаров до N (клоны помечены суффиксом алиаса «-bn<число>»)
//   php dev/bench/rm/clone.php clean   удалить клонированные товары и их строки в индексе списков
// После клонирования индекс списков и итоги категорий пересобираются штатными командами RadicalMart
// (radicalmart:categories:items_resave, radicalmart:categories:totals) — см. dev/bench/rm/README.md.
require __DIR__ . '/../../boot.php';

$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$arg = $argv[1] ?? '';
$p = '#__radicalmart_products';
$ci = '#__radicalmart_categories_items';
$where = "alias REGEXP '-bn[0-9]+$'";

$db->setQuery("DELETE FROM $ci WHERE item_id IN (SELECT id FROM $p WHERE $where) AND type = 'product'")->execute();
$db->setQuery("DELETE FROM $p WHERE $where")->execute();
if ($arg === 'clean') {
    echo "clean: товаров теперь " . $db->setQuery("SELECT COUNT(*) FROM $p")->loadResult() . "\n";
    exit;
}

$n = (int) $arg;
$base = (int) $db->setQuery("SELECT COUNT(*) FROM $p")->loadResult();
$extra = $n - $base;
if ($extra <= 0) {
    exit("уже $base товаров\n");
}
$src = (int) $db->setQuery("SELECT COUNT(*) FROM $p WHERE state = 1 AND meta_variability = 0")->loadResult();
echo "исходных простых опубликованных: $src, клонов: $extra\n";

$db->setQuery('SET SESSION cte_max_recursion_depth = 2000000')->execute();
$sql = "INSERT INTO $p (title, alias, code, category, category_pathway, category_route, categories_additional, categories_all, meta_variability,
    created, created_by, modified, modified_by, introtext, `fulltext`, search_text, prices, stock, in_stock, shipping, fields, state, media, params, changelogs, plugins, language)
  WITH RECURSIVE seq(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM seq WHERE i < $extra),
  s AS (SELECT q.*, ROW_NUMBER() OVER (ORDER BY q.id) - 1 AS rn FROM (SELECT * FROM $p WHERE state = 1 AND meta_variability = 0) q)
  SELECT CONCAT(s.title, ' ', seq.i), CONCAT(s.alias, '-bn', seq.i), CONCAT(s.code, '-bn', seq.i), s.category, s.category_pathway, s.category_route,
    s.categories_additional, s.categories_all, 0,
    DATE_ADD('2023-10-01', INTERVAL MOD(CRC32(CONCAT('d', seq.i)), 1095 * 86400) SECOND), s.created_by, s.modified, s.modified_by,
    s.introtext, s.`fulltext`, s.search_text, s.prices, s.stock, s.in_stock, s.shipping, s.fields, 1, s.media, s.params, '{}', s.plugins, s.language
  FROM seq JOIN s ON s.rn = MOD(seq.i, $src)";
$t = microtime(true);
$db->setQuery($sql)->execute();
printf("вставлено клонов: %d за %.1f с; товаров теперь %d\n", $db->getAffectedRows(), microtime(true) - $t, $db->setQuery("SELECT COUNT(*) FROM $p")->loadResult());
