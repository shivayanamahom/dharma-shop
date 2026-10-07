<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Order;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Подсказки «Наследуется: …» для полей правил оформления заказа в формах товара и категории: показывают, какое значение
 * и откуда будет взято, если поле оставить пустым.
 */
final class InheritHints
{
    /**
     * @param   array{values: array<string, mixed>, sources: array<string, int|string>}  $inherited  результат OrderRules::forCategory()
     * @param   string|null  $group   группа полей формы (у категории — params) или null
     * @param   string       $prefix  префикс имён полей (у категории — order_)
     */
    public static function apply(Form $form, OrderRules $rules, array $inherited, ?string $group, string $prefix): void
    {
        foreach (OrderRules::FIELDS as $field) {
            $name = $prefix . $field;

            if (!$form->getField($name, $group)) {
                continue;
            }

            $value = self::format($field, $inherited['values'][$field]);
            $source = $rules->sourceLabel($inherited['sources'][$field]);

            if (in_array($field, ['stock_tracked', 'backorder'], true)) {
                $form->setFieldAttribute($name, 'description', Text::sprintf('COM_DHARMASHOP_ORDER_INHERIT_DESC', $value, $source), $group);
            } else {
                $form->setFieldAttribute($name, 'hint', Text::sprintf('COM_DHARMASHOP_ORDER_INHERIT_HINT', $value . ' (' . $source . ')'), $group);
            }
        }
    }

    private static function format(string $field, mixed $value): string
    {
        return match ($field) {
            'qty_min', 'qty_step' => self::number((float) $value),
            'qty_max' => (float) $value > 0 ? self::number((float) $value) : Text::_('COM_DHARMASHOP_ORDER_UNLIMITED'),
            'unit' => (string) $value !== '' ? (string) $value : Text::_('COM_DHARMASHOP_ORDER_NO_UNIT'),
            default => Text::_((int) $value === 1 ? 'JYES' : 'JNO'),
        };
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
