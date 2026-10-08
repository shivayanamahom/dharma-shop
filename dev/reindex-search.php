<?php
// Dev-only: полная пересборка поискового индекса (`#__dharmashop_search`) на тестовом сайте — то же, что делает «Перестроить индекс».
//   php dev/reindex-search.php
require __DIR__ . '/boot.php';
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$indexer = new \Dharma\Component\DharmaShop\Administrator\Catalog\SearchIndexer($db);
$last = 0;
$total = 0;
while (true) {
    $ids = $db->setQuery($db->getQuery(true)->select('id')->from('#__dharmashop_products')->where('id > ' . $last)->order('id'), 0, 1000)->loadColumn();
    if (!$ids) { break; }
    $indexer->rebuildRange((int) $ids[0], (int) end($ids));
    $last = (int) end($ids);
    $total += count($ids);
}
echo "товаров в индексе: {$total}\n";
