<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Search;

\defined('_JEXEC') or die;

/**
 * Нормализация текста для поиска: индекс (`#__dharmashop_search`) и запрос посетителя приводятся к одному виду —
 * нижний регистр, «ё» как «е», всё кроме букв и цифр — пробелы. Один код на запись и чтение, чтобы они не расходились.
 */
final class SearchText
{
    /** Слова короче этой длины в FULLTEXT-индекс InnoDB не попадают (innodb_ft_min_token_size по умолчанию 3). */
    public const MIN_INDEXED = 3;

    public static function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(['ё', 'Ё'], 'е', mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * Слова запроса: нормализованные, без повторов, не больше $max штук.
     *
     * @return  string[]
     */
    public static function terms(string $query, int $max = 8): array
    {
        $words = array_values(array_unique(array_filter(explode(' ', self::normalize($query)), static fn (string $word): bool => $word !== '')));

        return array_slice($words, 0, $max);
    }
}
