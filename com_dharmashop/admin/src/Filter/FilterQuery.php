<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Условия фильтра в SQL списка (C84). Состав, состояние, доступ и поиск задаёт `productsQuery()`; здесь добавляются только условия
 * выбора посетителя — полуприсоединения `EXISTS` по индексам `field_values` и витрины. Поле варианта находит товар, если подходит любой
 * из его вариантов (EXISTS не размножает товары). Все числа — приведённые к int/float значения состояния; пользовательский текст сюда не попадает.
 */
final class FilterQuery
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Добавляет условия состояния в запрос списка.
     *
     * @param   QueryInterface  $query  запрос со списком товаров; таблица товаров с псевдонимом `p`
     * @param   bool            $hasPc  в запросе есть витрина `pc` (список категории): цена и наличие берутся из её индекса
     */
    public function apply(QueryInterface $query, FilterState $state, FilterDefinition $definition, bool $hasPc): void
    {
        foreach ($this->conditions($state, $definition, $hasPc) as $condition) {
            $query->where($condition);
        }
    }

    /**
     * Условия отдельными строками (для подсчёта значений «каскада» и тестов).
     *
     * @return  string[]
     */
    public function conditions(FilterState $state, FilterDefinition $definition, bool $hasPc): array
    {
        $db = $this->db;
        $values = $db->quoteName('#__dharmashop_field_values');
        $out = [];
        $n = 0;

        foreach ($state->options as $fieldId => $ids) {
            $field = $definition->fields[$fieldId] ?? null;

            if ($field === null || !$ids) {
                continue;
            }

            if ($field->logic() === 'and') {
                foreach ($ids as $optionId) {
                    $a = 'fv' . ++$n;
                    $out[] = "EXISTS (SELECT 1 FROM $values $a WHERE $a.product_id = p.id AND $a.field_id = " . (int) $fieldId . " AND $a.option_id = " . (int) $optionId . ')';
                }

                continue;
            }

            $a = 'fv' . ++$n;
            $out[] = "EXISTS (SELECT 1 FROM $values $a WHERE $a.product_id = p.id AND $a.field_id = " . (int) $fieldId . " AND $a.option_id IN (" . implode(',', array_map('intval', $ids)) . '))';
        }

        foreach ($state->ranges as $fieldId => $bounds) {
            $field = $definition->fields[$fieldId] ?? null;

            if ($field === null) {
                continue;
            }

            $a = 'fv' . ++$n;
            $where = $field->kind === FilterField::RANGE
                // Диапазон значения пересекается с выбранным: начало не позже верхней границы, конец (или начало) не раньше нижней.
                ? implode(' AND ', array_filter([
                    $bounds['max'] !== null ? "$a.num <= " . self::float($bounds['max']) : '',
                    $bounds['min'] !== null ? "COALESCE($a.num_end, $a.num) >= " . self::float($bounds['min']) : '',
                ]))
                : implode(' AND ', array_filter([
                    $bounds['min'] !== null ? "$a.num >= " . self::float($bounds['min']) : '',
                    $bounds['max'] !== null ? "$a.num <= " . self::float($bounds['max']) : '',
                ]));

            if ($where !== '') {
                $out[] = "EXISTS (SELECT 1 FROM $values $a WHERE $a.product_id = p.id AND $a.field_id = " . (int) $fieldId . " AND $where)";
            }
        }

        if ($state->price !== null) {
            // Любой вариант попадает в диапазон: минимальная цена не выше верхней границы, максимальная не ниже нижней.
            $minColumn = $hasPc ? 'pc.price_sort' : 'p.price_min';

            if ($state->price['max'] !== null) {
                $out[] = "$minColumn <= " . self::float($state->price['max']);
            }

            if ($state->price['min'] !== null) {
                $out[] = 'p.price_max >= ' . self::float($state->price['min']);
            }
        }

        $categories = $db->quoteName('#__dharmashop_product_categories');

        foreach ([[$state->brands, 'br', true], [$state->badges, 'bd', true], [$state->categories, 'sc', false]] as [$ids, $alias, $direct]) {
            if ($ids) {
                $out[] = "EXISTS (SELECT 1 FROM $categories $alias WHERE $alias.product_id = p.id AND $alias.catid IN (" . implode(',', array_map('intval', $ids)) . ") AND $alias.state = 1" . ($direct ? " AND $alias.direct = 1" : '') . ')';
            }
        }

        if ($state->inStock) {
            $out[] = 'p.in_stock = 1';
        }

        if ($state->onSale) { $out[] = 'p.on_sale = 1'; }

        return $out;
    }

    private static function float(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
