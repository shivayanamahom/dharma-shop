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
 * Что можно фильтровать в категории (C84): поля с их значениями, подкатегории, бренды и бейджи. Читается `FilterDefinitionLoader` (кэш);
 * по нему `FilterState` сопоставляет алиасы из адреса с номерами и отбрасывает неизвестное.
 */
final class FilterDefinition
{
    /**
     * @param   array<int, FilterField>                  $fields         id → поле
     * @param   array<int, array{alias: string, title: string}>  $subcategories  прямые подкатегории
     * @param   array<int, array{alias: string, title: string}>  $brands         опубликованные бренды (доступные посетителю)
     * @param   array<int, array{alias: string, title: string, color: string}>  $badges  опубликованные бейджи
     */
    public function __construct(
        public readonly int $catid,
        public readonly array $fields = [],
        public readonly array $subcategories = [],
        public readonly array $brands = [],
        public readonly array $badges = [],
    ) {
    }

    public function fieldByAlias(string $alias): ?FilterField
    {
        foreach ($this->fields as $field) {
            if ($field->alias === $alias) {
                return $field;
            }
        }

        return null;
    }

    /** Номер по алиасу в справочнике (подкатегории, бренды, бейджи): id или 0. @param array<int, array{alias: string}> $directory */
    public static function idByAlias(array $directory, string $alias): int
    {
        foreach ($directory as $id => $row) {
            if ($row['alias'] === $alias) {
                return (int) $id;
            }
        }

        return 0;
    }
}
