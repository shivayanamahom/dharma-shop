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
 * Скидка на цену: сумма («500») или проценты («10%»). Ввод в форме — одной строкой; в базе значение и признак процентов.
 */
final class Discount
{
    /**
     * Разбирает ввод.
     *
     * @return  array{value: float, percent: bool}|null  null — скидки нет (пустой ввод)
     *
     * @throws  \InvalidArgumentException  COM_DHARMASHOP_ERROR_DISCOUNT_FORMAT, если ввод не похож на «500» или «10%»
     */
    public static function parse(mixed $input): ?array
    {
        $text = trim((string) $input);

        if ($text === '') {
            return null;
        }

        if (!preg_match('/^(\d+(?:[.,]\d{1,2})?)\s*(%?)$/u', $text, $m)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_ERROR_DISCOUNT_FORMAT');
        }

        $value = (float) str_replace(',', '.', $m[1]);

        return $value > 0 ? ['value' => $value, 'percent' => $m[2] === '%'] : null;
    }

    /**
     * Проверяет скидку относительно цены: проценты — от 0 до 100 (не включая), сумма — меньше цены; без цены скидки быть не может.
     *
     * @throws  \InvalidArgumentException  COM_DHARMASHOP_ERROR_DISCOUNT_RANGE
     */
    public static function assertValid(?array $discount, ?float $price): void
    {
        if ($discount === null) {
            return;
        }

        if ($price === null || ($discount['percent'] && $discount['value'] >= 100) || (!$discount['percent'] && $discount['value'] >= $price)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_ERROR_DISCOUNT_RANGE');
        }
    }

    /** Цена после скидки, два знака, не ниже нуля. */
    public static function apply(float $price, float $value, bool $percent): float
    {
        return max(0.0, round($percent ? $price * (1 - $value / 100) : $price - $value, 2));
    }

    /** Значение для поля формы: «500» или «10%». */
    public static function format(null|float|string $value, bool|int|string|null $percent): string
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return '';
        }

        $number = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $number . ((int) $percent === 1 ? '%' : '');
    }
}
