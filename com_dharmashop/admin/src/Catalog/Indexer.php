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
    public function setCategories(
        int $productId,
        int $mainCatid,
        array $extraCatids = [],
        int $brandId = 0,
        array $badgeIds = [],
        string $newProductPosition = 'last',
        array $categoryPositions = []
    ): void
    {
        $previousCategoryIds = $this->categoryIds($productId);
        $direct = array_merge([$mainCatid], $extraCatids, [$brandId], $badgeIds);
        $direct = array_values(array_unique(array_filter(array_map('intval', $direct))));
        $in = implode(',', $direct);

        // Старые прямые привязки временно становятся унаследованными: если они ещё являются
        // родителями другой назначенной категории, rebuildRange сохранит их ручной порядок.
        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_product_categories SET direct = 0, is_main = 0 '
            . 'WHERE product_id = :p AND direct = 1 AND catid NOT IN (' . $in . ')',
            [':p' => [$productId, ParameterType::INTEGER]]
        );
        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_product_categories SET direct = 1, is_main = IF(catid = ' . $mainCatid . ', 1, 0) '
            . 'WHERE product_id = :p AND catid IN (' . $in . ')',
            [':p' => [$productId, ParameterType::INTEGER]]
        );

        Sql::exec(
            $this->db,
            'INSERT IGNORE INTO #__dharmashop_product_categories (catid, product_id, direct, is_main, state, in_stock, created, title_sort) '
            . 'SELECT c.id, :p, 1, 0, 0, 0, NOW(), \'\' FROM #__categories c WHERE c.id IN (' . $in . ')',
            [':p' => [$productId, ParameterType::INTEGER]]
        );

        $this->rebuildRange($productId, $productId);

        // Только новые связи получают стартовую позицию из настроек; при обычном сохранении
        // уже существующий ручной порядок товара не перезаписывается.
        if (in_array($newProductPosition, ['first', 'last'], true)) {
            foreach (array_diff($this->categoryIds($productId), $previousCategoryIds) as $catid) {
                $position = (string) ($categoryPositions[(int) $catid] ?? $newProductPosition);
                $position = in_array($position, ['first', 'last'], true) ? $position : $newProductPosition;
                $this->setCategoryOrder($productId, [(int) $catid => ['action' => $position]]);
            }
        }
    }

    /** @return int[] Категории магазина, в которых индекс уже содержит товар (прямые и унаследованные). */
    private function categoryIds(int $productId): array
    {
        $db = $this->db;
        $query = $db->createQuery()
            ->select('pc.catid')
            ->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = pc.catid')
            ->where('pc.product_id = :p')->where('c.extension = ' . $db->quote('com_dharmashop'))
            ->bind(':p', $productId, ParameterType::INTEGER);

        return array_map('intval', $db->setQuery($query)->loadColumn());
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
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, '
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
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, '
            . 'pc.created = p.created, pc.title_sort = LEFT(LOWER(p.title), 60), pc.is_main = IF(pc.catid = p.catid, 1, 0) '
            . 'WHERE p.catid IN (' . $in . ')'
        )->execute();
    }

    /** Пересобирает родительские строки и копии столбцов для товаров с id от $from до $to включительно. */
    public function rebuildRange(int $from, int $to): void
    {
        $ids = $this->db->setQuery(
            Sql::query($this->db, 'SELECT id FROM #__dharmashop_products WHERE id BETWEEN :f AND :t', [
                ':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER],
            ])
        )->loadColumn();

        $this->rebuildParentsForProducts(array_map('intval', $ids));
        // Поисковый индекс — часть той же пересборки («Перестроить индекс» в админке).
        (new SearchIndexer($this->db))->rebuildRange($from, $to);
    }

    /**
     * Пересобирает родительские строки и копии столбцов для товаров из списка (после перемещения категории, бренда, бейджа).
     *
     * @param   int[]  $productIds
     */
    public function rebuildProducts(array $productIds, int $chunk = 500): void
    {
        foreach (array_chunk(array_values(array_unique(array_map('intval', $productIds))), $chunk) as $ids) {
            (new \Dharma\Component\DharmaShop\Administrator\Filter\FacetIndexer($this->db))->track($ids, function () use ($ids): void {
                $this->rebuildParentsForProducts($ids);
            });
        }
    }

    /**
     * Перестраивает родительские строки: лишние удаляются, недостающие добавляются. Строки, нужные и до, и после, не трогаются,
     * поэтому их ручной порядок сохраняется без временных таблиц (им нужна отдельная привилегия БД, которой у части хостингов нет).
     *
     * @param int[] $productIds
     */
    private function rebuildParentsForProducts(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$ids) {
            return;
        }

        $in = implode(',', $ids);

        // Родители, которые нужны сейчас: предки прямых категорий товара в своём типе (категории, бренды).
        $needed = 'SELECT DISTINCT d.product_id, anc.id AS catid FROM #__dharmashop_product_categories d '
            . 'JOIN #__categories sub ON sub.id = d.catid '
            . 'JOIN #__categories anc ON anc.extension = sub.extension AND anc.lft < sub.lft AND anc.rgt > sub.rgt '
            . 'WHERE d.direct = 1 AND d.product_id IN (' . $in . ')';

        // Лишние родительские строки (прямая привязка к потомку исчезла).
        $this->db->setQuery(
            'DELETE pc FROM #__dharmashop_product_categories pc LEFT JOIN (' . $needed . ') need '
            . 'ON need.product_id = pc.product_id AND need.catid = pc.catid '
            . 'WHERE pc.direct = 0 AND pc.product_id IN (' . $in . ') AND need.catid IS NULL'
        )->execute();

        // Недостающие; существующие строки (и их порядок) INSERT IGNORE не затрагивает.
        $this->db->setQuery(
            'INSERT IGNORE INTO #__dharmashop_product_categories (catid, product_id, direct, is_main, state, in_stock, created, title_sort) '
            . 'SELECT need.catid, need.product_id, 0, 0, 0, 0, NOW(), \'\' FROM (' . $needed . ') need'
        )->execute();

        $this->refreshProducts($ids);
    }

    /** Сохраняет порядок товара в каждой его категории; catid из запроса проверяется по строкам его индекса. */
    public function setCategoryOrder(int $productId, array $orders): void
    {
        $db = $this->db;

        foreach ($orders as $catid => $row) {
            $catid = (int) $catid;
            $row = (array) $row;
            $action = (string) ($row['action'] ?? 'manual');

            if ($catid <= 0 || !in_array($action, ['first', 'last', 'manual'], true)) {
                continue;
            }

            $sortOrder = max(-2147483648, min(2147483647, (int) ($row['sort_order'] ?? 0)));

            if ($action !== 'manual') {
                // Граница порядка в категории без самого товара: по одному точному обращению к индексу (catid, state, sort_order)
                // на состояние; товары в корзине не учитываются. Время не зависит от числа товаров в категории.
                $function = $action === 'first' ? 'MIN' : 'MAX';
                $edge = null;

                foreach ([0, 1, 2] as $state) {
                    $query = $db->createQuery()
                        ->select($function . '(pc.sort_order)')
                        ->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
                        ->where('pc.catid = :c')->where('pc.state = :s')->where('pc.product_id <> :p')
                        ->bind(':c', $catid, ParameterType::INTEGER)
                        ->bind(':s', $state, ParameterType::INTEGER)
                        ->bind(':p', $productId, ParameterType::INTEGER);
                    $value = $db->setQuery($query)->loadResult();

                    if ($value !== null) {
                        $edge = $edge === null ? (int) $value : ($action === 'first' ? min($edge, (int) $value) : max($edge, (int) $value));
                    }
                }

                $sortOrder = $edge === null ? 0 : max(-2147483648, min(2147483647, $edge + ($action === 'first' ? -1 : 1)));
            }

            Sql::exec($db,
                'UPDATE #__dharmashop_product_categories pc JOIN #__categories c ON c.id = pc.catid '
                . 'SET pc.sort_order = :s WHERE pc.product_id = :p AND pc.catid = :c AND c.extension = \'com_dharmashop\'',
                [':s' => [$sortOrder, ParameterType::INTEGER], ':p' => [$productId, ParameterType::INTEGER], ':c' => [$catid, ParameterType::INTEGER]]
            );
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
            . 'SET pc.state = p.state, pc.price_sort = p.price_min, pc.in_stock = p.in_stock, '
            . 'pc.created = p.created, pc.title_sort = LEFT(LOWER(p.title), 60), pc.is_main = IF(pc.catid = p.catid, 1, 0) '
            . 'WHERE pc.product_id BETWEEN :f AND :t',
            [':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER]]
        );
    }
}
