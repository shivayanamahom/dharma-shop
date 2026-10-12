<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Filter;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;

/** Additive installer migration; never executed by a frontend request. */
final class FilterStorage
{
    public static function upgrade(DatabaseInterface $db): void
    {
        $hasColumn = isset($db->getTableColumns('#__dharmashop_products')['on_sale']);
        $indexes = array_column($db->setQuery('SHOW INDEX FROM #__dharmashop_products')->loadAssocList(), 'Key_name');
        if (!$hasColumn) {
            $db->setQuery('ALTER TABLE #__dharmashop_products ADD on_sale tinyint unsigned NOT NULL DEFAULT 0')->execute();
        }
        // DDL в MySQL не откатывается: индекс `idx_sale` создаётся ПОСЛЕ заполнения и служит отметкой завершения. Если заполнение
        // прервалось, следующий запуск повторит его (столбец уже есть, индекса ещё нет).
        if (!in_array('idx_sale', $indexes, true)) {
            $db->setQuery('UPDATE #__dharmashop_products p LEFT JOIN (SELECT v.product_id, MAX(CASE WHEN vp.price_final < vp.price THEN 1 ELSE 0 END) AS sale FROM #__dharmashop_variants v JOIN #__dharmashop_variant_prices vp ON vp.variant_id=v.id AND vp.price_list_id=1 WHERE v.state=1 GROUP BY v.product_id) s ON s.product_id=p.id SET p.on_sale=COALESCE(s.sale,0)')->execute();
            $db->setQuery('ALTER TABLE #__dharmashop_products ADD KEY idx_sale (state,on_sale,id)')->execute();
        }
        // Сортировка по цене и названию страниц без категории (корень каталога, поиск, модуль товаров) не должна сортировать весь каталог.
        if (!in_array('idx_state_price', $indexes, true)) { $db->setQuery('ALTER TABLE #__dharmashop_products ADD KEY idx_state_price (state,price_min,id)')->execute(); }
        if (!in_array('idx_state_title', $indexes, true)) { $db->setQuery('ALTER TABLE #__dharmashop_products ADD KEY idx_state_title (state,title,id)')->execute(); }
        // Поиск коротких слов (меньше порога FULLTEXT) идёт по началу названия.
        $searchIndexes = array_column($db->setQuery('SHOW INDEX FROM #__dharmashop_search')->loadAssocList(), 'Key_name');
        if (!in_array('idx_title', $searchIndexes, true)) { $db->setQuery('ALTER TABLE #__dharmashop_search ADD KEY idx_title (title_norm)')->execute(); }
    }
}
