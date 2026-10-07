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
 * Эффективная цена варианта: базовая или со скидкой, если скидка действует на момент $now.
 * Цена «по запросу» — null. Значение пересчитывается при сохранении и задачей планировщика.
 */
final class PriceResolver
{
    /**
     * @param   float|null   $price      базовая цена (null — по запросу)
     * @param   float|null   $discount   скидка: сумма или проценты (null — скидки нет)
     * @param   bool         $percent    true — $discount в процентах
     * @param   string|null  $start      начало скидки (UTC, 'Y-m-d H:i:s') или null
     * @param   string|null  $end        конец скидки (UTC) или null
     * @param   string       $now        текущий момент (UTC)
     */
    public function effective(?float $price, ?float $discount, bool $percent, ?string $start, ?string $end, string $now): ?float
    {
        if ($price === null) {
            return null;
        }

        if ($discount === null || $discount <= 0) {
            return $price;
        }

        if (($start !== null && $start > $now) || ($end !== null && $end < $now)) {
            return $price;
        }

        return Discount::apply($price, $discount, $percent);
    }
}
