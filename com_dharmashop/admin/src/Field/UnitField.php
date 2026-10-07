<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Выпадающий список единиц измерения с дополнительными вариантами из настроек магазина. */
class UnitField extends ListField
{
    protected $type = 'Unit';

    /** @var array<string, string> Сокращение => языковой ключ подписи. */
    private const BUILTIN_UNITS = [
        'шт' => 'COM_DHARMASHOP_UNIT_PIECE',
        'ед.' => 'COM_DHARMASHOP_UNIT_ITEM',
        'компл.' => 'COM_DHARMASHOP_UNIT_SET',
        'набор' => 'COM_DHARMASHOP_UNIT_KIT',
        'пар' => 'COM_DHARMASHOP_UNIT_PAIR',
        'упак.' => 'COM_DHARMASHOP_UNIT_PACKAGE',
        'кор.' => 'COM_DHARMASHOP_UNIT_BOX',
        'пач.' => 'COM_DHARMASHOP_UNIT_PACK',
        'рул.' => 'COM_DHARMASHOP_UNIT_ROLL',
        'лист' => 'COM_DHARMASHOP_UNIT_SHEET',
        'бут.' => 'COM_DHARMASHOP_UNIT_BOTTLE',
        'банка' => 'COM_DHARMASHOP_UNIT_JAR',
        'м' => 'COM_DHARMASHOP_UNIT_METER',
        'пог. м' => 'COM_DHARMASHOP_UNIT_LINEAR_METER',
        'см' => 'COM_DHARMASHOP_UNIT_CENTIMETER',
        'мм' => 'COM_DHARMASHOP_UNIT_MILLIMETER',
        'км' => 'COM_DHARMASHOP_UNIT_KILOMETER',
        'м²' => 'COM_DHARMASHOP_UNIT_SQUARE_METER',
        'см²' => 'COM_DHARMASHOP_UNIT_SQUARE_CENTIMETER',
        'га' => 'COM_DHARMASHOP_UNIT_HECTARE',
        'м³' => 'COM_DHARMASHOP_UNIT_CUBIC_METER',
        'г' => 'COM_DHARMASHOP_UNIT_GRAM',
        'кг' => 'COM_DHARMASHOP_UNIT_KILOGRAM',
        'т' => 'COM_DHARMASHOP_UNIT_TONNE',
        'мл' => 'COM_DHARMASHOP_UNIT_MILLILITER',
        'л' => 'COM_DHARMASHOP_UNIT_LITER',
        'ч' => 'COM_DHARMASHOP_UNIT_HOUR',
        'сут.' => 'COM_DHARMASHOP_UNIT_DAY',
        'порц.' => 'COM_DHARMASHOP_UNIT_PORTION',
    ];

    /** @var array<string, string> Сокращение => языковой ключ единиц веса. */
    private const WEIGHT_UNITS = [
        'мг' => 'COM_DHARMASHOP_UNIT_MILLIGRAM',
        'г' => 'COM_DHARMASHOP_UNIT_GRAM',
        'кг' => 'COM_DHARMASHOP_UNIT_KILOGRAM',
        'т' => 'COM_DHARMASHOP_UNIT_TONNE',
        'oz' => 'COM_DHARMASHOP_UNIT_OUNCE',
        'lb' => 'COM_DHARMASHOP_UNIT_POUND',
    ];

    /** @var array<string, string> Сокращение => языковой ключ единиц размеров. */
    private const DIMENSION_UNITS = [
        'мм' => 'COM_DHARMASHOP_UNIT_MILLIMETER',
        'см' => 'COM_DHARMASHOP_UNIT_CENTIMETER',
        'м' => 'COM_DHARMASHOP_UNIT_METER',
        'км' => 'COM_DHARMASHOP_UNIT_KILOMETER',
        'in' => 'COM_DHARMASHOP_UNIT_INCH',
        'ft' => 'COM_DHARMASHOP_UNIT_FOOT',
    ];

    protected function getOptions(): array
    {
        $params = ComponentHelper::getParams('com_dharmashop');
        $group = (string) ($this->element['unitgroup'] ?: 'sale');
        [$builtinUnits, $customParam] = match ($group) {
            'weight' => [self::WEIGHT_UNITS, 'shipping_weight_units_custom'],
            'dimension' => [self::DIMENSION_UNITS, 'shipping_dimension_units_custom'],
            default => [self::BUILTIN_UNITS, 'order_units_custom'],
        };
        $custom = trim((string) $params->get($customParam, ''));
        $units = [];

        if ($custom !== '') {
            foreach (preg_split('/\R/u', $custom) ?: [] as $unit) {
                $unit = trim($unit);

                if ($unit !== '' && mb_strlen($unit) <= 20) {
                    $units[$unit] = $unit;
                }
            }
        } else {
            foreach ($builtinUnits as $unit => $label) {
                $units[$unit] = Text::_($label);
            }
        }

        // Не теряем ранее сохранённую единицу, если её убрали из списка настроек.
        $current = trim((string) $this->value);

        if ($current !== '' && !isset($units[$current])) {
            $units[$current] = $current;
        }

        $options = [];

        foreach ($units as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, $label);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
