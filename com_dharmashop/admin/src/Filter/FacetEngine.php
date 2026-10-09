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
 * Значения, счётчики и границы фильтра (C84). Базовое состояние категории читается из предрасчёта `filter_facets` / `filter_ranges`
 * одним индексным запросом (кэшируется). Каскад — числа под текущий выбор — считает модель списка по кандидатам `productsQuery()`
 * (`CategoryModel::getFacets()`), а здесь лежат общие части: чтение базы, границы цены и склейка результата.
 */
final class FacetEngine
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Базовое состояние категории: у каких значений есть товары и сколько, границы чисел.
     *
     * @return  array{options: array<int, array<int, int>>, ranges: array<int, array{min: float, max: float}>}
     */
    public function base(int $catid): array
    {
        return FilterCache::remember('base:' . $catid, function () use ($catid): array {
            $db = $this->db;
            $options = [];
            $ranges = [];
            $query = $db->createQuery()->select(['field_id', 'option_id', 'products'])->from($db->quoteName('#__dharmashop_filter_facets'))
                ->where('catid = :c')->where('products > 0')->bind(':c', $catid, ParameterType::INTEGER);

            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $options[(int) $row->field_id][(int) $row->option_id] = (int) $row->products;
            }

            $query = $db->createQuery()->select(['field_id', 'num_min', 'num_max'])->from($db->quoteName('#__dharmashop_filter_ranges'))
                ->where('catid = :c')->bind(':c', $catid, ParameterType::INTEGER);

            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                if ($row->num_min !== null) {
                    $ranges[(int) $row->field_id] = ['min' => (float) $row->num_min, 'max' => (float) $row->num_max];
                }
            }

            return ['options' => $options, 'ranges' => $ranges];
        });
    }

    /**
     * Сколько товаров категории списка относится к записям справочника: бренды и бейджи (`direct = 1`) или подкатегории.
     *
     * @param   int[]  $ids  номера записей справочника
     *
     * @return  array<int, int>  запись → число товаров (только > 0)
     */
    public function directoryCounts(int $catid, array $ids, bool $direct): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return [];
        }

        sort($ids);

        return FilterCache::remember('directory:' . $catid . ':' . ($direct ? 'd' : 's') . ':' . implode(',', $ids), function () use ($catid, $ids, $direct): array {
            $db = $this->db;
            $list = implode(',', $ids);
            $pc = $db->quoteName('#__dharmashop_product_categories');

            if ($catid > 0 && $direct) {
                // Товары категории списка, имеющие прямую привязку к бренду или бейджу.
                $sql = "SELECT b.catid, COUNT(*) AS n FROM $pc a JOIN $pc b ON b.product_id = a.product_id AND b.direct = 1 AND b.state = 1 AND b.catid IN ($list) WHERE a.catid = $catid AND a.state = 1 GROUP BY b.catid";
            } else {
                $sql = "SELECT catid, COUNT(*) AS n FROM $pc WHERE catid IN ($list) AND state = 1" . ($direct ? ' AND direct = 1' : '') . ' GROUP BY catid';
            }

            $counts = [];

            foreach ($db->setQuery($sql)->loadObjectList() as $row) {
                $counts[(int) $row->catid] = (int) $row->n;
            }

            return $counts;
        });
    }

    /**
     * Границы цены списка категории по индексу витрины (для ползунка и подсказок полей).
     *
     * @return  array{min: float, max: float}|null  null — нет товаров с ценой
     */
    public function price(int $catid): ?array
    {
        return FilterCache::remember('price:' . $catid, function () use ($catid): ?array {
            $db = $this->db;

            if ($catid > 0) {
                $query = $db->createQuery()->select(['MIN(price_sort) AS mn', 'MAX(price_sort) AS mx'])->from($db->quoteName('#__dharmashop_product_categories'))
                    ->where('catid = :c')->where('state = 1')->where('price_sort > 0')->bind(':c', $catid, ParameterType::INTEGER);
            } else {
                $query = $db->createQuery()->select(['MIN(price_min) AS mn', 'MAX(price_min) AS mx'])->from($db->quoteName('#__dharmashop_products'))
                    ->where('state = 1')->where('price_min > 0');
            }

            $row = $db->setQuery($query)->loadObject();

            return $row && $row->mn !== null ? ['min' => (float) $row->mn, 'max' => (float) $row->mx] : null;
        });
    }
}
