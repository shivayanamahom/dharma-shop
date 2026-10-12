<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Производные поля товара (price_min, price_max, in_stock, variants_count) из его вариантов.
 */
final class ProductRebuilder
{
    public function __construct(private readonly DatabaseInterface $db, private readonly int $priceListId = 1)
    {
    }

    public function rebuild(int $productId): void
    {
        $this->rebuildRange($productId, $productId);
    }

    /** Пересчитывает производные поля товаров с id от $from до $to включительно одним запросом. */
    public function rebuildRange(int $from, int $to): void
    {
        $priceList = $this->priceListId;
        $sql = 'UPDATE #__dharmashop_products p LEFT JOIN ('
            . 'SELECT v.product_id AS pid, MIN(vp.price_final) AS mn, MAX(vp.price_final) AS mx, MAX(v.in_stock) AS ins, COUNT(*) AS c, MAX(CASE WHEN vp.price_final < vp.price THEN 1 ELSE 0 END) AS sale '
            . 'FROM #__dharmashop_variants v '
            . 'LEFT JOIN #__dharmashop_variant_prices vp ON vp.variant_id = v.id AND vp.price_list_id = :pl '
            . 'WHERE v.product_id BETWEEN :f AND :t AND v.state = 1 GROUP BY v.product_id'
            . ') a ON a.pid = p.id '
            . 'SET p.price_min = a.mn, p.price_max = a.mx, p.in_stock = COALESCE(a.ins, 0), p.variants_count = COALESCE(a.c, 0), p.on_sale = COALESCE(a.sale, 0) '
            . 'WHERE p.id BETWEEN :f2 AND :t2';

        Sql::exec($this->db, $sql, [
            ':pl' => [$priceList, ParameterType::INTEGER],
            ':f' => [$from, ParameterType::INTEGER],
            ':t' => [$to, ParameterType::INTEGER],
            ':f2' => [$from, ParameterType::INTEGER],
            ':t2' => [$to, ParameterType::INTEGER],
        ]);
        $groups = $this->db->setQuery('SELECT DISTINCT group_id FROM #__dharmashop_products WHERE id BETWEEN ' . $from . ' AND ' . $to . ' AND group_id IS NOT NULL')->loadColumn();
        foreach ($groups as $group) { (new GroupService($this->db))->rebuild((int) $group); }
    }

    /** Пересчитывает производные поля товаров, основная категория которых входит в список. @param int[] $catids */
    public function rebuildCategories(array $catids): void
    {
        $in = implode(',', array_values(array_unique(array_filter(array_map('intval', $catids)))));

        if ($in === '') {
            return;
        }

        $priceList = $this->priceListId;
        $sql = 'UPDATE #__dharmashop_products p LEFT JOIN ('
            . 'SELECT v.product_id AS pid, MIN(vp.price_final) AS mn, MAX(vp.price_final) AS mx, MAX(v.in_stock) AS ins, COUNT(*) AS c, MAX(CASE WHEN vp.price_final < vp.price THEN 1 ELSE 0 END) AS sale '
            . 'FROM #__dharmashop_variants v JOIN #__dharmashop_products pp ON pp.id = v.product_id AND pp.catid IN (' . $in . ') '
            . 'LEFT JOIN #__dharmashop_variant_prices vp ON vp.variant_id = v.id AND vp.price_list_id = :pl '
            . 'WHERE v.state = 1 GROUP BY v.product_id'
            . ') a ON a.pid = p.id '
            . 'SET p.price_min = a.mn, p.price_max = a.mx, p.in_stock = COALESCE(a.ins, 0), p.variants_count = COALESCE(a.c, 0), p.on_sale = COALESCE(a.sale, 0) '
            . 'WHERE p.catid IN (' . $in . ')';

        Sql::exec($this->db, $sql, [':pl' => [$priceList, ParameterType::INTEGER]]);
        foreach ($this->db->setQuery('SELECT DISTINCT group_id FROM #__dharmashop_products WHERE catid IN (' . $in . ') AND group_id IS NOT NULL')->loadColumn() as $group) {
            (new GroupService($this->db))->rebuild((int) $group);
        }
    }
}
