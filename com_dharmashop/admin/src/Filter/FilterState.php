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

use Joomla\CMS\Component\ComponentHelper;

\defined('_JEXEC') or die;

/**
 * Выбор посетителя в фильтре (C84): безопасное состояние, собранное из параметров адреса `filter[…]` по справочнику категории.
 * Всё неизвестное и лишнее отбрасывается молча; границы приводятся к числам; число условий и значений ограничено.
 *
 * Формат адреса (ключи — алиасы):
 *   filter[f][<поле>][]=<значение>             список; filter[f][<поле>][min|max]=число — число или диапазон
 *   filter[price][min|max]=число               цена
 *   filter[brand][]=<бренд>, filter[badge][]=<бейдж>, filter[cat][]=<подкатегория>
 *   filter[stock]=1, filter[sale]=1            в наличии, по акции
 */
final class FilterState
{
    public const MAX_FIELDS = 12;
    public const MAX_VALUES = 50;
    private const BOUND = 1.0E12;

    /**
     * @param   array<int, int[]>                                   $options   поле → выбранные значения (id)
     * @param   array<int, array{min: ?float, max: ?float}>         $ranges    поле → границы (число / диапазон)
     * @param   array{min: ?float, max: ?float}|null                $price     цена
     * @param   int[]                                               $brands
     * @param   int[]                                               $badges
     * @param   int[]                                               $categories  подкатегории
     */
    public function __construct(
        public readonly array $options = [],
        public readonly array $ranges = [],
        public readonly ?array $price = null,
        public readonly array $brands = [],
        public readonly array $badges = [],
        public readonly array $categories = [],
        public readonly bool $inStock = false,
        public readonly bool $onSale = false,
    ) {
    }

    /**
     * Состояние из массива `filter` запроса. Ключ `search` здесь не разбирается (его читает поиск).
     *
     * @param   array<string, mixed>  $filter
     */
    public static function fromArray(array $filter, FilterDefinition $definition): self
    {
        $options = [];
        $ranges = [];
        $settings = ComponentHelper::getParams('com_dharmashop');
        $maxFields = max(1, min(24, (int) $settings->get('filter_max_fields', self::MAX_FIELDS)));
        $maxValues = max(1, min(100, (int) $settings->get('filter_max_values', self::MAX_VALUES)));
        $fields = isset($filter['f']) && is_array($filter['f']) ? $filter['f'] : [];

        foreach (array_slice($fields, 0, $maxFields, true) as $alias => $raw) {
            if (count($options) + count($ranges) >= $maxFields) {
                break;
            }

            $field = is_string($alias) ? $definition->fieldByAlias($alias) : null;

            if ($field === null || !is_array($raw)) {
                continue;
            }

            if ($field->kind === FilterField::OPTION) {
                $ids = [];

                foreach (array_slice($raw, 0, $maxValues) as $value) {
                    $option = is_scalar($value) ? $field->option(mb_substr((string) $value, 0, 190)) : null;

                    if ($option !== null) {
                        $ids[$option->id] = $option->id;
                    }
                }

                if ($ids) {
                    $options[$field->id] = array_values($ids);
                }

                continue;
            }

            $bounds = self::bounds($raw);

            if ($bounds !== null) {
                $ranges[$field->id] = $bounds;
            }
        }

        $price = isset($filter['price']) && is_array($filter['price']) ? self::bounds($filter['price']) : null;

        return new self(
            $options,
            $ranges,
            $price,
            self::directory($filter['brand'] ?? null, $definition->brands),
            self::directory($filter['badge'] ?? null, $definition->badges),
            self::directory($filter['cat'] ?? null, $definition->subcategories),
            self::flag($filter['stock'] ?? null),
            self::flag($filter['sale'] ?? null),
        );
    }

    /** Нет ни одного условия (список без фильтра, канонический адрес). */
    public function isEmpty(): bool
    {
        return !$this->options && !$this->ranges && $this->price === null && !$this->brands && !$this->badges && !$this->categories && !$this->inStock && !$this->onSale;
    }

    /** Число заданных условий (полей и системных фильтров). */
    public function activeCount(): int
    {
        return count($this->options) + count($this->ranges) + ($this->price !== null ? 1 : 0) + ($this->brands ? 1 : 0) + ($this->badges ? 1 : 0)
            + ($this->categories ? 1 : 0) + ($this->inStock ? 1 : 0) + ($this->onSale ? 1 : 0);
    }

    /**
     * Канонический массив `filter` для адреса: алиасы, стабильный порядок ключей и значений. Равные состояния дают равные массивы
     * (канонический адрес, ключ кэша).
     *
     * @return  array<string, mixed>
     */
    public function toArray(FilterDefinition $definition): array
    {
        $out = [];
        $fields = [];

        foreach ($definition->fields as $field) {
            if (isset($this->options[$field->id])) {
                $aliases = [];

                foreach ($field->options as $option) {
                    if (in_array($option->id, $this->options[$field->id], true)) {
                        $aliases[] = $option->alias;
                    }
                }

                $fields[$field->alias] = $aliases;
            } elseif (isset($this->ranges[$field->id])) {
                $fields[$field->alias] = self::boundsArray($this->ranges[$field->id]);
            }
        }

        if ($fields) {
            ksort($fields);
            $out['f'] = $fields;
        }

        if ($this->price !== null) {
            $out['price'] = self::boundsArray($this->price);
        }

        foreach (['brand' => [$this->brands, $definition->brands], 'badge' => [$this->badges, $definition->badges], 'cat' => [$this->categories, $definition->subcategories]] as $key => [$ids, $directory]) {
            $aliases = [];

            foreach ($directory as $id => $row) {
                if (in_array($id, $ids, true)) {
                    $aliases[] = $row['alias'];
                }
            }

            if ($aliases) {
                $out[$key] = $aliases;
            }
        }

        if ($this->inStock) {
            $out['stock'] = '1';
        }

        if ($this->onSale) {
            $out['sale'] = '1';
        }

        return $out;
    }

    /** Ключ состояния для кэша: каноническое содержимое. */
    public function key(): string
    {
        $options = $this->options; $ranges = $this->ranges;
        ksort($options); ksort($ranges);
        foreach ($options as &$ids) { sort($ids, SORT_NUMERIC); } unset($ids);
        $directories = [$this->brands, $this->badges, $this->categories];
        foreach ($directories as &$ids) { sort($ids, SORT_NUMERIC); } unset($ids);
        return md5(json_encode([$options, $ranges, $this->price, $directories, $this->inStock, $this->onSale]) ?: '');
    }

    /** Состояние без одного поля (для чисел «каскада»: выбор самого поля не сужает его значения). */
    public function withoutField(int $fieldId): self
    {
        $options = $this->options;
        $ranges = $this->ranges;
        unset($options[$fieldId], $ranges[$fieldId]);

        return new self($options, $ranges, $this->price, $this->brands, $this->badges, $this->categories, $this->inStock, $this->onSale);
    }

    public function withoutSystem(string $key): self
    {
        return new self($this->options, $this->ranges, $key === 'price' ? null : $this->price,
            $key === 'brand' ? [] : $this->brands, $key === 'badge' ? [] : $this->badges,
            $key === 'cat' ? [] : $this->categories, $key === 'stock' ? false : $this->inStock, $key === 'sale' ? false : $this->onSale);
    }

    /** Границы из массива min/max: числа, зажатые в разумные пределы; пустое — null. @param array<mixed> $raw @return array{min: ?float, max: ?float}|null */
    private static function bounds(array $raw): ?array
    {
        $min = self::number($raw['min'] ?? null);
        $max = self::number($raw['max'] ?? null);

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    /** @param array{min: ?float, max: ?float} $bounds @return array<string, string> */
    private static function boundsArray(array $bounds): array
    {
        $out = [];

        foreach (['min', 'max'] as $side) {
            if ($bounds[$side] !== null) {
                $out[$side] = rtrim(rtrim(number_format($bounds[$side], 6, '.', ''), '0'), '.');
            }
        }

        return $out;
    }

    private static function number(mixed $value): ?float
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));

        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        return strlen($text) <= 64 && is_finite((float) $text) ? max(-self::BOUND, min(self::BOUND, (float) $text)) : null;
    }

    /**
     * Алиасы справочника → номера (только известные, без повторов, в порядке справочника).
     *
     * @param   mixed                                      $raw
     * @param   array<int, array{alias: string}>           $directory
     *
     * @return  int[]
     */
    private static function directory(mixed $raw, array $directory): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $wanted = [];

        foreach (array_slice($raw, 0, max(1, min(100, (int) ComponentHelper::getParams('com_dharmashop')->get('filter_max_values', self::MAX_VALUES)))) as $value) {
            if (is_scalar($value)) {
                $wanted[mb_substr((string) $value, 0, 190)] = true;
            }
        }

        $ids = [];

        foreach ($directory as $id => $row) {
            if (isset($wanted[$row['alias']])) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private static function flag(mixed $value): bool
    {
        return is_scalar($value) && in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
