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

\defined('_JEXEC') or die;

/**
 * Поле, по которому фильтруется список (C84). `kind`: option — значения из списка (`option_id`), number — число (`num`), range — диапазон (`num`…`num_end`).
 * Настройки вывода и логики берутся из `fields.params.filter` (значения по умолчанию — здесь).
 */
final class FilterField
{
    public const OPTION = 'option';
    public const NUMBER = 'number';
    public const RANGE = 'range';

    /**
     * @param   array<int, FilterOption>  $options   значения (id → значение), только у kind = option
     * @param   array<string, mixed>      $settings  filter.* из params поля: control, logic (or|and), sort, limit, expanded, counts
     */
    public function __construct(
        public readonly int $id,
        public readonly string $alias,
        public readonly string $title,
        public readonly string $type,
        public readonly string $kind,
        public readonly int $level,
        public readonly string $unit,
        public readonly array $options = [],
        public readonly array $settings = [],
    ) {
    }

    public function option(string $alias): ?FilterOption
    {
        foreach ($this->options as $option) {
            if ($option->alias === $alias) {
                return $option;
            }
        }

        return null;
    }

    /** Логика нескольких выбранных значений одного поля: or (по умолчанию) или and. */
    public function logic(): string
    {
        return (string) ($this->settings['logic'] ?? 'or') === 'and' ? 'and' : 'or';
    }
}
