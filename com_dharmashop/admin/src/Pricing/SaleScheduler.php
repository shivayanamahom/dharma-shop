<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Pricing;

use Dharma\Component\DharmaShop\Administrator\Catalog\Indexer;
use Dharma\Component\DharmaShop\Administrator\Catalog\ProductRebuilder;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Пересчёт эффективной цены у вариантов, у которых скидка началась или закончилась: price_final → цена товара → индекс витрины.
 * Вызывается командой «Пересчитать скидки по датам» и задачей планировщика. Одним набором запросов, без перебора товаров.
 */
final class SaleScheduler
{
    /** Выражение эффективной цены; суффикс делает имена параметров уникальными при повторном использовании в одном запросе. */
    private static function finalExpr(string $suffix): string
    {
        return 'CASE WHEN vp.price IS NULL THEN NULL '
            . 'WHEN vp.discount IS NOT NULL AND vp.discount > 0 AND (vp.discount_start IS NULL OR vp.discount_start <= :ns' . $suffix . ') '
            . 'AND (vp.discount_end IS NULL OR vp.discount_end >= :ne' . $suffix . ') '
            . 'THEN GREATEST(0, ROUND(IF(vp.discount_percent = 1, vp.price * (1 - vp.discount / 100), vp.price - vp.discount), 2)) ELSE vp.price END';
    }

    /** @return array<string, array{0: string}> */
    private static function nowParams(string $now, string ...$suffixes): array
    {
        $params = [];

        foreach ($suffixes as $suffix) {
            $params[':ns' . $suffix] = [$now];
            $params[':ne' . $suffix] = [$now];
        }

        return $params;
    }

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ProductRebuilder $rebuilder,
        private readonly Indexer $indexer,
    ) {
    }

    /** @return int число затронутых товаров */
    public function run(): int
    {
        $now = (new Date())->toSql();
        $products = Sql::query(
            $this->db,
            'SELECT DISTINCT v.product_id FROM #__dharmashop_variant_prices vp JOIN #__dharmashop_variants v ON v.id = vp.variant_id '
            . 'WHERE NOT (vp.price_final <=> (' . self::finalExpr('a') . '))',
            self::nowParams($now, 'a')
        );
        $ids = array_map('intval', $this->db->setQuery($products)->loadColumn());

        if (!$ids) {
            return 0;
        }

        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_variant_prices vp SET vp.price_final = ' . self::finalExpr('b') . ' WHERE NOT (vp.price_final <=> (' . self::finalExpr('c') . '))',
            self::nowParams($now, 'b', 'c')
        );

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($chunk as $id) {
                $this->rebuilder->rebuild($id);
            }

            $this->indexer->refreshProducts($chunk);
        }

        \Dharma\Component\DharmaShop\Administrator\Filter\FilterCache::forget();
        return count($ids);
    }
}
