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

\defined('_JEXEC') or die;

/**
 * Эффективная цена варианта: базовая или акционная, если акция действует на момент $now.
 * Цена «по запросу» — null. Значение пересчитывается при сохранении и задачей планировщика.
 */
final class PriceResolver
{
    /**
     * @param   float|null   $price      базовая цена (null — по запросу)
     * @param   float|null   $salePrice  акционная цена
     * @param   string|null  $saleStart  начало акции (UTC, 'Y-m-d H:i:s') или null
     * @param   string|null  $saleEnd    конец акции (UTC) или null
     * @param   string       $now        текущий момент (UTC)
     */
    public function effective(?float $price, ?float $salePrice, ?string $saleStart, ?string $saleEnd, string $now): ?float
    {
        if ($price === null) {
            return null;
        }

        if ($salePrice === null || $salePrice >= $price) {
            return $price;
        }

        if ($saleStart !== null && $saleStart > $now) {
            return $price;
        }

        if ($saleEnd !== null && $saleEnd < $now) {
            return $price;
        }

        return $salePrice;
    }
}
