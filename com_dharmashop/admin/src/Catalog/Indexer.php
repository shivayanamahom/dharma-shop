<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Витринный индекс: строки «товар — категория» (включая все родительские категории) с копиями столбцов сортировки.
 * Источник правды — таблица товаров; индекс всегда можно перестроить (rebuildRange / rebuildAll).
 */
final class Indexer
{
    public const EXTENSION = CategoryTypes::CATEGORY;

    public const EXTENSION_BRAND = CategoryTypes::BRAND;

    public const EXTENSION_BADGE = CategoryTypes::BADGE;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Задаёт прямые «категории» товара всех типов и перестраивает его строки индекса:
     * основная и дополнительные категории (секция com_dharmashop), бренд (com_dharmashop.brand), бейджи (com_dharmashop.badge).
     * Ручной порядок (sort_order) у сохраняемых строк не теряется.
     *
     * @param   int[]  $extraCatids  дополнительные категории
     * @param   int    $brandId      бренд (0 — без бренда)
     * @param   int[]  $badgeIds     бейджи
     */
    public function setCategories(int $productId, int $mainCatid, array $extraCatids = [], int $brandId = 0, array $badgeIds = []): void
    {
        $direct = array_merge([$mainCatid], $extraCatids, [$brandId], $badgeIds);
        $direct = array_values(array_unique(array_filter(array_map('intval', $direct))));
        $in = implode(',', $direct);

        Sql::exec(
            $this->db,
            'DELETE FROM #__dharmashop_product_categories WHERE product_id = :p AND (direct = 0 OR catid NOT IN (' . $in . '))',
            [':p' => [$productId, ParameterType::INTEGER]]
        );

        Sql::exec(
            $this->db,
            'INSERT IGNORE INTO #__dharmashop_product_categories (catid, product_id, direct, is_main, state, in_stock, created, title_sort) '
            . 'SELECT c.id, :p, 1, 0, 0, 0, NOW(), \'\' FROM #__categories c WHERE c.id IN (' . $in . ')',
            [':p' => [$productId, ParameterType::INTEGER]]
        );

        $this->rebuildRange($productId, $productId);
    }

    /**
     * Прямые привязки товара без основной категории, по типам (для формы).
     *
     * @return  array{extra: int[], brand: int, badges: int[]}
     */
    public function getTaxonomy(int $productId): array
    {
        $query = $this->db->createQuery()
            ->select('pc.catid, c.extension')
            ->from($this->db->quoteName('#__dharmashop_product_categories', 'pc'))
            ->join('INNER', '#__categories AS c ON c.id = pc.catid')
            ->where('pc.product_id = :p')->where('pc.direct = 1')->where('pc.is_main = 0')
            ->bind(':p', $productId, ParameterType::INTEGER);

        $result = ['extra' => [], 'brand' => 0, 'badges' => []];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            match ($row->extension) {
                self::EXTENSION_BRAND => $result['brand'] = (int) $row->catid,
                self::EXTENSION_BADGE => $result['badges'][] = (int) $row->catid,
                default => $result['extra'][] = (int) $row->catid,
            };
        }

        return $result;
    }

    /** Копирует в индекс актуальные состояние, цену, наличие и т. п. товара (родительские категории не пересчитываются). */
    public function refreshProduct(int $productId): void
    {
        $this->refreshRange($productId, $productId);
    }

    /** @param int[] $productIds */
    public function refreshProducts(array $productIds): void
    {
        $in = implode(',', array_map('intval', $productIds));

        if ($in === '') {
            return;
        }

        $this->db->setQuery(
            'UPDATE #__dharmashop_product_categories pc JOIN #__dharmashop_products p ON p.id = pc.product_id '
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, pc.featured = p.featured, '
            . 'pc.created = p.created, pc.title_sort = LEFT(LOWER(p.title), 60), pc.is_main = IF(pc.catid = p.catid, 1, 0) '
            . 'WHERE pc.product_id IN (' . $in . ')'
        )->execute();
    }

    /** Обновляет копии в индексе для товаров, основная категория которых входит в список. @param int[] $catids */
    public function refreshCategories(array $catids): void
    {
        $in = implode(',', array_values(array_unique(array_filter(array_map('intval', $catids)))));

        if ($in === '') {
            return;
        }

        $this->db->setQuery(
            'UPDATE #__dharmashop_product_categories pc JOIN #__dharmashop_products p ON p.id = pc.product_id '
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, pc.featured = p.featured, '
            . 'pc.created = p.created, pc.title_sort = LEFT(LOWER(p.title), 60), pc.is_main = IF(pc.catid = p.catid, 1, 0) '
            . 'WHERE p.catid IN (' . $in . ')'
        )->execute();
    }

    /** Пересобирает родительские строки и копии столбцов для товаров с id от $from до $to включительно. */
    public function rebuildRange(int $from, int $to): void
    {
        $range = [':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER]];

        Sql::exec($this->db, 'DELETE FROM #__dharmashop_product_categories WHERE direct = 0 AND product_id BETWEEN :f AND :t', $range);

        Sql::exec(
            $this->db,
            'INSERT IGNORE INTO #__dharmashop_product_categories (catid, product_id, direct, is_main, state, in_stock, created, title_sort) '
            . 'SELECT anc.id, pc.product_id, 0, 0, 0, 0, NOW(), \'\' '
            . 'FROM #__dharmashop_product_categories pc '
            . 'JOIN #__categories sub ON sub.id = pc.catid '
            . 'JOIN #__categories anc ON anc.extension = sub.extension AND anc.lft < sub.lft AND anc.rgt > sub.rgt '
            . 'WHERE pc.direct = 1 AND pc.product_id BETWEEN :f AND :t',
            $range
        );

        $this->refreshRange($from, $to);
    }

    /**
     * Пересобирает родительские строки и копии столбцов для товаров из списка (после перемещения категории, бренда, бейджа).
     *
     * @param   int[]  $productIds
     */
    public function rebuildProducts(array $productIds, int $chunk = 500): void
    {
        foreach (array_chunk(array_values(array_unique(array_map('intval', $productIds))), $chunk) as $ids) {
            $in = implode(',', $ids);

            Sql::exec($this->db, 'DELETE FROM #__dharmashop_product_categories WHERE direct = 0 AND product_id IN (' . $in . ')');
            Sql::exec(
                $this->db,
                'INSERT IGNORE INTO #__dharmashop_product_categories (catid, product_id, direct, is_main, state, in_stock, created, title_sort) '
                . 'SELECT anc.id, pc.product_id, 0, 0, 0, 0, NOW(), \'\' '
                . 'FROM #__dharmashop_product_categories pc '
                . 'JOIN #__categories sub ON sub.id = pc.catid '
                . 'JOIN #__categories anc ON anc.extension = sub.extension AND anc.lft < sub.lft AND anc.rgt > sub.rgt '
                . 'WHERE pc.direct = 1 AND pc.product_id IN (' . $in . ')'
            );
            $this->refreshProducts($ids);
        }
    }

    /**
     * Перестраивает индекс товаров, привязанных к записи и её потомкам (вызывать после перемещения записи в дереве).
     *
     * @return  int  число обработанных товаров
     */
    public function rebuildCategorySubtree(int $categoryId): int
    {
        $query = Sql::query(
            $this->db,
            'SELECT DISTINCT pc.product_id FROM #__dharmashop_product_categories pc '
            . 'JOIN #__categories c ON c.id = pc.catid '
            . 'JOIN #__categories root ON root.id = :root AND c.extension = root.extension AND c.lft >= root.lft AND c.rgt <= root.rgt '
            . 'WHERE pc.direct = 1',
            [':root' => [$categoryId, ParameterType::INTEGER]]
        );

        $ids = array_map('intval', $this->db->setQuery($query)->loadColumn());

        if ($ids) {
            $this->rebuildProducts($ids);
        }

        return count($ids);
    }

    /** Полная пересборка кусками по ключу; возвращает число обработанных товаров. */
    public function rebuildAll(?callable $progress = null, int $chunk = 2000): int
    {
        $done = 0;
        $last = 0;

        while (true) {
            $ids = $this->db->setQuery(
                $this->db->createQuery()->select('id')->from('#__dharmashop_products')
                    ->where('id > :last')->order('id')->bind(':last', $last, ParameterType::INTEGER),
                0,
                $chunk
            )->loadColumn();

            if (!$ids) {
                break;
            }

            $from = (int) $ids[0];
            $to = (int) end($ids);
            $this->rebuildRange($from, $to);
            $last = $to;
            $done += count($ids);

            if ($progress) {
                $progress($done);
            }
        }

        // Строки индекса несуществующих товаров.
        $this->db->setQuery(
            'DELETE pc FROM #__dharmashop_product_categories pc LEFT JOIN #__dharmashop_products p ON p.id = pc.product_id WHERE p.id IS NULL'
        )->execute();

        return $done;
    }

    public function deleteProduct(int $productId): void
    {
        Sql::exec($this->db, 'DELETE FROM #__dharmashop_product_categories WHERE product_id = :p', [':p' => [$productId, ParameterType::INTEGER]]);
    }

    /** Обновляет в индексе копии состояния, цены, наличия и т. п. для товаров с id от $from до $to. */
    public function refreshRange(int $from, int $to): void
    {
        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_product_categories pc JOIN #__dharmashop_products p ON p.id = pc.product_id '
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, pc.featured = p.featured, '
            . 'pc.created = p.created, pc.title_sort = LEFT(LOWER(p.title), 60), pc.is_main = IF(pc.catid = p.catid, 1, 0) '
            . 'WHERE pc.product_id BETWEEN :f AND :t',
            [':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER]]
        );
    }
}
