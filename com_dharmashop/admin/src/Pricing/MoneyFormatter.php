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

use Joomla\CMS\Language\Text;
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

    /** Включена ли замена нулевой цены текстом (настройки магазина, вкладка «Корзина»). */
    public function replacesZero(): bool
    {
        return (int) $this->params->get('price_zero_replace', 0) === 1;
    }

    /** Текст вместо нулевой цены; если не задан — «Цена по запросу». */
    public function zeroText(): string
    {
        $text = trim((string) $this->params->get('price_zero_text', ''));

        return $text !== '' ? $text : Text::_('COM_DHARMASHOP_PRODUCT_PRICE_REQUEST');
    }

    /** Текст для бесплатного товара (правило «Цена 0 — бесплатно» у товара или категории). */
    public function freeText(): string
    {
        return Text::_('COM_DHARMASHOP_PRODUCT_PRICE_FREE');
    }

    /**
     * Цена для витрины: нулевая сумма заменяется текстом, если замена включена; пустое значение остаётся пустым.
     *
     * @param  bool  $free  у товара «цена 0 — бесплатно»: нулевая цена показывается как «Бесплатно» и не заменяется текстом «по запросу»
     */
    public function price(null|float|string $amount, bool $free = false): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        if ((float) $amount === 0.0) {
            if ($free) {
                return $this->freeText();
            }

            return $this->replacesZero() ? $this->zeroText() : $this->format($amount);
        }

        return $this->format($amount);
    }

    /** Диапазон цен для витрины: «от — до»; если обе границы нулевые и замена включена, выводится только текст. */
    public function range(null|float|string $min, null|float|string $max = null, bool $free = false): string
    {
        if ($min === null || $min === '') {
            return '';
        }

        if ($free && (float) $min === 0.0) {
            return $this->freeText();
        }

        if ($this->replacesZero() && (float) $min === 0.0) {
            // Нулевая цена не участвует в диапазоне: «0 — 5000» превращается в «5000».
            return $max !== null && $max !== '' && (float) $max > 0.0 ? $this->format($max) : $this->zeroText();
        }

        return $this->format($min) . ($max !== null && $max !== '' && (string) $min !== (string) $max ? ' — ' . $this->format($max) : '');
    }
}
