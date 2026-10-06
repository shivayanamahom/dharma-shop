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

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Вывод денежных сумм по настройкам магазина (знак, положение, число знаков). Расчётов не делает.
 */
final class MoneyFormatter
{
    public function __construct(private readonly Registry $params)
    {
    }

    public function format(null|float|string $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $decimals = max(0, min(4, (int) $this->params->get('currency_decimals', 0)));
        $number = number_format((float) $amount, $decimals, ',', "\u{00A0}");
        $symbol = (string) $this->params->get('currency_symbol', '₽');

        return $this->params->get('currency_position', 'after') === 'before'
            ? $symbol . "\u{00A0}" . $number
            : $number . "\u{00A0}" . $symbol;
    }
}
