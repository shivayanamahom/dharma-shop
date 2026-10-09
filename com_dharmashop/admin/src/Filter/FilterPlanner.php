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
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Выбор формы запроса списка по плотности условий (C84, замеры F0 — `docs/FILTER.md` §9.1).
 *
 * Плотное условие (значение есть у заметной доли товаров) быстрее всего проверять обходом индекса сортировки категории с остановкой на
 * последнем нужном совпадении (`FORCE INDEX`); оптимизатор MySQL в этом случае сортирует десятки тысяч подходящих строк (40–430 мс вместо 1,5–20).
 * Редкое условие и глубокая страница — обычный запрос: оптимизатор сам начинает с редкого значения поля. Правило: индекс сортировки, если
 * `(смещение + лимит) / плотность` не превышает бюджет просмотра строк. Плотность оценивается по долям значений полей в каталоге
 * (быстрый подсчёт по индексу, кэш; позже — таблица предрасчёта), условия считаются независимыми.
 */
final class FilterPlanner
{
    /** Сколько строк индекса категории допустимо просмотреть до 24-го совпадения. */
    public const SCAN_BUDGET = 30000;

    /** Индекс витрины под сортировку списка (`ORDER BY` повторяет его столбцы). */
    private const INDEXES = [
        'order' => 'idx_l_order',
        'price_asc' => 'idx_l_price',
        'price_desc' => 'idx_l_price',
        'new' => 'idx_l_new',
        'title' => 'idx_l_title',
    ];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** Имя индекса для подсказки `FORCE INDEX` или null (оставить выбор оптимизатору). */
    public function indexHint(FilterState $state, FilterDefinition $definition, int $catid, string $ordering, int $offset, int $limit): ?string
    {
        $index = self::INDEXES[$ordering] ?? null;

        if ($state->isEmpty() || $catid <= 0 || $index === null) {
            return null;
        }

        // Небольшая категория: обход индекса всегда дёшев.
        if ($this->categorySize($catid) <= self::SCAN_BUDGET) {
            return $index;
        }

        $density = $this->density($state, $definition, $catid);

        if ($density <= 0.0) {
            return null;
        }

        return ($offset + $limit) / $density <= self::SCAN_BUDGET ? $index : null;
    }

    /** Оценка доли товаров, подходящих под все условия (0…1). */
    public function density(FilterState $state, FilterDefinition $definition, int $catid): float
    {
        $total = max(1, $this->totalProducts());
        $density = 1.0;

        foreach ($state->options as $fieldId => $ids) {
            $field = $definition->fields[$fieldId] ?? null;
            $counts = $this->optionCounts($fieldId);

            if ($field !== null && $field->logic() === 'and') {
                foreach ($ids as $id) {
                    $density *= min(1.0, ($counts[$id] ?? 0) / $total);
                }

                continue;
            }

            $density *= min(1.0, array_sum(array_map(static fn (int $id): int => $counts[$id] ?? 0, $ids)) / $total);
        }

        // Числа и диапазоны, цена, наличие — без гистограмм: нейтральные оценки.
        $density *= 0.3 ** count($state->ranges);
        $density *= $state->price !== null ? 0.5 : 1.0;
        $density *= $state->inStock ? 0.8 : 1.0;

        foreach ([$state->brands, $state->badges] as $ids) {
            if ($ids) {
                $density *= min(1.0, $this->directCount($ids) / $total);
            }
        }

        if ($state->categories) {
            $density *= min(1.0, $this->directCount($state->categories) / max(1, $this->categorySize($catid)));
        }

        return $density;
    }

    /** Число товаров категории в витрине (кэш). */
    public function categorySize(int $catid): int
    {
        return (int) FilterCache::remember('size:' . $catid, function () use ($catid): int {
            $db = $this->db;
            $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__dharmashop_product_categories'))
                ->where('catid = :c')->where('state = 1')->bind(':c', $catid, ParameterType::INTEGER);

            return (int) $db->setQuery($query)->loadResult();
        });
    }

    private function totalProducts(): int
    {
        return (int) FilterCache::remember('total', function (): int {
            $db = $this->db;

            return (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__dharmashop_products'))->where('state = 1'))->loadResult();
        });
    }

    /** @return array<int, int> значение → число строк (покрывающий индекс `idx_option`) */
    private function optionCounts(int $fieldId): array
    {
        return FilterCache::remember('optcounts:' . $fieldId, function () use ($fieldId): array {
            $db = $this->db;
            $query = $db->createQuery()->select(['option_id', 'COUNT(*) AS n'])->from($db->quoteName('#__dharmashop_field_values'))
                ->where('field_id = :f')->where('option_id IS NOT NULL')->group('option_id')->bind(':f', $fieldId, ParameterType::INTEGER);
            $counts = [];

            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $counts[(int) $row->option_id] = (int) $row->n;
            }

            return $counts;
        });
    }

    /** Число прямых привязок товаров к категориям (бренды, бейджи, подкатегории). @param int[] $catids */
    private function directCount(array $catids): int
    {
        sort($catids);

        return (int) FilterCache::remember('direct:' . implode(',', $catids), function () use ($catids): int {
            $db = $this->db;
            $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__dharmashop_product_categories'))
                ->where('catid IN (' . implode(',', array_map('intval', $catids)) . ')')->where('state = 1');

            return (int) $db->setQuery($query)->loadResult();
        });
    }
}
