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

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Встроенная галерея товара: строки `#__dharmashop_field_media` с `field_id = 0` (поля-галереи этапа 2 используют свои `field_id`).
 * Первое изображение — главное: его путь и alt копируются в `products.cover` / `cover_alt` (по ним список и витрина берут обложку).
 */
final class GalleryRepository
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** @return array<int, array{path: string, alt: string, title: string}> */
    public function load(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->setQuery(
            'SELECT path, alt, title FROM #__dharmashop_field_media WHERE product_id = ' . $productId
            . ' AND field_id = 0 AND variant_id IS NULL ORDER BY ordering, id'
        )->loadAssocList();

        return array_map(static fn (array $row): array => ['path' => (string) $row['path'], 'alt' => (string) $row['alt'], 'title' => (string) $row['title']], $rows ?: []);
    }

    /**
     * Заменяет галерею товара и обновляет обложку.
     *
     * @param   array<int, array{path: string, alt?: string, title?: string}>  $items  уже очищенные элементы (ImageUploader::filterGallery)
     *
     * @return  string[]  пути файлов, которых в галерее больше нет (кандидаты на удаление с диска)
     */
    public function save(int $productId, array $items): array
    {
        $old = array_column($this->load($productId), 'path');
        $db = $this->db;

        $db->setQuery('DELETE FROM #__dharmashop_field_media WHERE product_id = ' . $productId . ' AND field_id = 0 AND variant_id IS NULL')->execute();

        $order = 0;
        $values = [];

        foreach ($items as $item) {
            $values[] = '(' . $productId . ', NULL, 0, ' . $db->quote((string) $item['path']) . ', ' . $db->quote((string) ($item['alt'] ?? '')) . ', '
                . $db->quote((string) ($item['title'] ?? '')) . ', ' . $order++ . ')';
        }

        if ($values) {
            $db->setQuery('INSERT INTO #__dharmashop_field_media (product_id, variant_id, field_id, path, alt, title, ordering) VALUES ' . implode(', ', $values))->execute();
        }

        $first = $items[0] ?? null;
        $db->setQuery(
            'UPDATE #__dharmashop_products SET cover = ' . ($first ? $db->quote((string) $first['path']) : 'NULL')
            . ', cover_alt = ' . ($first && ($first['alt'] ?? '') !== '' ? $db->quote((string) $first['alt']) : 'NULL')
            . ' WHERE id = ' . $productId
        )->execute();

        return array_values(array_diff($old, array_column($items, 'path')));
    }

    /**
     * Удаляет галерею удалённого товара.
     *
     * @return  string[]  пути файлов (кандидаты на удаление с диска)
     */
    public function deleteForProduct(int $productId): array
    {
        $paths = array_column($this->load($productId), 'path');
        $this->db->setQuery('DELETE FROM #__dharmashop_field_media WHERE product_id = ' . $productId . ' AND field_id = 0')->execute();

        return $paths;
    }
}
