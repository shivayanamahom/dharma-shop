<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Search;

use Dharma\Component\DharmaShop\Administrator\Search\SearchText;

\defined('_JEXEC') or die;

/**
 * Запрос поиска товаров: подзапрос «товар → релевантность» по индексу `#__dharmashop_search` (FULLTEXT, слова — по началу:
 * `+слово*`) и по артикулу (префикс по уникальному индексу вариантов). Слова короче 3 символов в FULLTEXT-индекс не попадают,
 * поэтому для них проверяется начало слова в названии среди уже найденных (или только это, если все слова короткие).
 * Значения запроса всегда привязываются параметрами; в булев запрос попадают только буквы и цифры.
 */
final class ProductSearch
{
    /** Наибольшая длина запроса посетителя. */
    public const MAX_LENGTH = 100;

    /**
     * Текст поискового запроса из адреса страницы: `?filter[search]=…` (общий формат параметров фильтра каталога; в нём же будут
     * остальные условия модуля фильтра). Старый `?q=` читается для совместимости.
     */
    public static function fromInput(\Joomla\Input\Input $input): string
    {
        $filter = $input->get('filter', [], 'array');
        $raw = is_array($filter) && isset($filter['search']) && is_scalar($filter['search']) ? (string) $filter['search'] : (string) $input->getString('q', '');

        return self::clean($raw);
    }

    /**
     * Очищенный текст запроса (без управляющих символов, не длиннее MAX_LENGTH).
     */
    public static function clean(string $raw): string
    {
        $raw = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw) ?? '';

        return mb_substr(trim($raw), 0, self::MAX_LENGTH);
    }

    /**
     * Подзапрос «product_id, score» и значения его параметров; null — искать нечего (нет слов).
     *
     * @return  array{0: string, 1: array<string, string>}|null
     */
    public static function matched(string $raw): ?array
    {
        $terms = SearchText::terms($raw);

        if (!$terms) {
            return null;
        }

        $long = array_values(array_filter($terms, static fn (string $term): bool => mb_strlen($term) >= SearchText::MIN_INDEXED));
        $short = array_values(array_diff($terms, $long));
        $binds = [];

        if ($long) {
            $boolean = implode(' ', array_map(static fn (string $term): string => '+' . $term . '*', $long));
            $binds[':dsft1'] = $boolean;
            $binds[':dsft2'] = $boolean;
            $score = 'MATCH(title_norm, body) AGAINST(:dsft1 IN BOOLEAN MODE)';
            $where = 'MATCH(title_norm, body) AGAINST(:dsft2 IN BOOLEAN MODE)';
        } else {
            $score = '1';
            $where = '1 = 1';
        }

        foreach ($short as $index => $term) {
            $binds[':dsa' . $index] = $term . '%';
            $binds[':dsb' . $index] = '% ' . $term . '%';
            $where .= ' AND (title_norm LIKE :dsa' . $index . ' OR title_norm LIKE :dsb' . $index . ')';
        }

        $branches = ['SELECT product_id, ' . $score . ' AS score FROM #__dharmashop_search WHERE ' . $where];
        $sku = self::skuPattern($raw);

        if ($sku !== null) {
            // Артикул, введённый целиком или началом: выше любых текстовых совпадений.
            $binds[':dssku'] = $sku;
            $branches[] = 'SELECT product_id, 1000 AS score FROM #__dharmashop_variants WHERE sku LIKE :dssku';
        }

        return ['SELECT product_id, MAX(score) AS score FROM (' . implode(' UNION ALL ', $branches) . ') AS u GROUP BY product_id', $binds];
    }

    /** Шаблон LIKE для артикула (один «слово без пробелов» из букв, цифр и . _ - /); иначе null. */
    private static function skuPattern(string $raw): ?string
    {
        $raw = self::clean($raw);

        if (mb_strlen($raw) < 2 || !preg_match('~^[\p{L}\p{N}._/\-]+$~u', $raw)) {
            return null;
        }

        return addcslashes($raw, '%_\\') . '%';
    }
}
