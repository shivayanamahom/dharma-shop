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

use Dharma\Component\DharmaShop\Administrator\Search\SearchText;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Поисковый индекс товаров (`#__dharmashop_search`): нормализованное название и «тело» — описание без разметки, «текст для поиска»
 * (синонимы), артикулы вариантов, значения строковых и списочных полей, названия категорий и бренда. Источник правды — таблицы
 * товаров; индекс всегда можно перестроить. Читает диапазон товаров несколькими запросами (без запроса на каждый товар).
 */
final class SearchIndexer
{
    /** Тело индекса ограничено: длинное описание не должно раздувать таблицу. */
    private const BODY_LIMIT = 20000;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** Пересобирает строки поиска товаров с id от $from до $to включительно. */
    public function rebuildRange(int $from, int $to): void
    {
        $params = [':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER]];
        $products = $this->db->setQuery(Sql::query(
            $this->db,
            'SELECT id, title, introtext, `fulltext`, search_text FROM #__dharmashop_products WHERE id BETWEEN :f AND :t',
            $params
        ))->loadObjectList();

        if (!$products) {
            return;
        }

        $parts = [];
        $collect = function (string $sql) use (&$parts, $params): void {
            foreach ($this->db->setQuery(Sql::query($this->db, $sql, $params))->loadRowList() as $row) {
                $parts[(int) $row[0]][] = (string) $row[1];
            }
        };

        // Артикулы, значения полей (строки и названия выбранных вариантов списка), категории и бренд.
        $collect('SELECT product_id, sku FROM #__dharmashop_variants WHERE product_id BETWEEN :f AND :t');
        $collect('SELECT product_id, str FROM #__dharmashop_field_values WHERE product_id BETWEEN :f AND :t AND str IS NOT NULL AND str <> \'\'');
        $collect(
            'SELECT fv.product_id, fo.title FROM #__dharmashop_field_values fv JOIN #__dharmashop_field_options fo ON fo.id = fv.option_id '
            . 'WHERE fv.product_id BETWEEN :f AND :t',
        );
        $collect(
            'SELECT pc.product_id, c.title FROM #__dharmashop_product_categories pc JOIN #__categories c ON c.id = pc.catid '
            . 'WHERE pc.product_id BETWEEN :f AND :t AND pc.direct = 1 AND c.extension IN (\'com_dharmashop\', \'com_dharmashop.brand\')',
        );

        $values = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $body = SearchText::normalize(implode(' ', array_merge(
                [(string) $product->introtext, (string) $product->fulltext, (string) $product->search_text],
                $parts[$id] ?? []
            )));
            $values[] = '(' . $id . ', ' . $this->db->quote(mb_substr(SearchText::normalize((string) $product->title), 0, 255))
                . ', ' . $this->db->quote(mb_substr($body, 0, self::BODY_LIMIT)) . ')';
        }

        foreach (array_chunk($values, 200) as $chunk) {
            $this->db->setQuery(
                'INSERT INTO ' . $this->db->quoteName('#__dharmashop_search') . ' (product_id, title_norm, body) VALUES ' . implode(',', $chunk)
                . ' ON DUPLICATE KEY UPDATE title_norm = VALUES(title_norm), body = VALUES(body)'
            )->execute();
        }
    }

    /** Строка поиска одного товара (после сохранения карточки). */
    public function rebuild(int $productId): void
    {
        $this->rebuildRange($productId, $productId);
    }

    public function delete(int $productId): void
    {
        Sql::exec($this->db, 'DELETE FROM #__dharmashop_search WHERE product_id = :p', [':p' => [$productId, ParameterType::INTEGER]]);
    }
}
