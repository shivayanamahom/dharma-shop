<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Search;

use Dharma\Component\DharmaShop\Administrator\Search\SearchText;
use Joomla\CMS\Component\ComponentHelper;

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
     * Каждое слово запроса раскрывается в варианты: само слово, синонимы из настроек, другая раскладка и транслитерация (если включены);
     * товар должен содержать каждое слово хотя бы в одном из вариантов. Мягкий режим ($fuzzy) — для запроса без результатов: слова
     * от 5 букв ищутся без двух последних, поэтому находятся слова с опечаткой в конце.
     *
     * @return  array{0: string, 1: array<string, string>}|null
     */
    public static function matched(string $raw, bool $fuzzy = false): ?array
    {
        $terms = SearchText::terms($raw);

        if (!$terms) {
            return null;
        }

        $settings = ComponentHelper::getParams('com_dharmashop');
        $synonyms = SearchText::parseSynonyms((string) $settings->get('search_synonyms', ''));
        $translit = (int) $settings->get('search_translit', 1) === 1;
        $binds = [];
        $groups = [];
        $score = '';
        $where = '';
        $shortWords = [];
        $counter = 0;
        $bind = static function (string $value) use (&$binds, &$counter): string {
            $name = ':dsv' . $counter++;
            $binds[$name] = $value;

            return $name;
        };

        foreach ($terms as $term) {
            $variants = SearchText::variants($term, $synonyms, $translit);
            $long = mb_strlen($term) >= SearchText::MIN_INDEXED;
            $words = [];

            foreach ($variants as $variant) {
                if ($fuzzy && mb_strlen($variant) >= 5) {
                    $variant = mb_substr($variant, 0, max(SearchText::MIN_INDEXED, mb_strlen($variant) - 2));
                }

                $words[] = $variant;
            }

            $words = array_values(array_unique($words));

            // Совпадение слова в начале слова названия: для слов короче 3 символов это же и условие отбора (FULLTEXT их не знает).
            $like = [];

            foreach ($long ? $words : [] as $word) {
                $like[] = 'title_norm LIKE ' . $bind($word . '%') . ' OR title_norm LIKE ' . $bind('% ' . $word . '%');
            }

            if ($long) {
                $indexed = array_values(array_filter($words, static fn (string $word): bool => mb_strlen($word) >= SearchText::MIN_INDEXED));
                $groups[] = count($indexed) > 1 ? '+(' . implode(' ', array_map(static fn (string $word): string => $word . '*', $indexed)) . ')' : '+' . $indexed[0] . '*';
                // Совпадение в названии важнее совпадения в описании: +100 за слово запроса в названии.
                $score .= ' + IF(' . implode(' OR ', $like) . ', 100, 0)';
            } else {
                $shortWords[] = $words;
            }
        }

        // Слова короче порога FULLTEXT не знает. Рядом с длинным словом это дешёвое уточнение уже найденных строк (любая позиция в
        // названии). Если длинных слов нет, условие отбирает строки само: ведущий `%` читал бы всю таблицу, поэтому только начало
        // названия — по префиксному индексу `idx_title`.
        foreach ($shortWords as $words) {
            $parts = [];

            foreach ($words as $word) {
                $parts[] = 'title_norm LIKE ' . $bind($word . '%');

                if ($groups) {
                    $parts[] = 'title_norm LIKE ' . $bind('% ' . $word . '%');
                }
            }

            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }

        if ($groups) {
            $boolean = implode(' ', $groups);
            $binds[':dsft1'] = $boolean;
            $binds[':dsft2'] = $boolean;
            $score = 'MATCH(title_norm, body) AGAINST(:dsft1 IN BOOLEAN MODE)' . $score;
            $where = 'MATCH(title_norm, body) AGAINST(:dsft2 IN BOOLEAN MODE)' . $where;
        } else {
            $score = '1';
            $where = '1 = 1' . $where;
        }

        // +50, если название начинается с запроса целиком.
        $score .= ' + IF(title_norm LIKE ' . $bind(implode(' ', $terms) . '%') . ', 50, 0)';
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
