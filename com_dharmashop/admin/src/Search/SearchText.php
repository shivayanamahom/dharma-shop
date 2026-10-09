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

    /** Соответствие клавиш русской и английской раскладок (ЙЦУКЕН ↔ QWERTY), только буквы. */
    private const LAYOUT = [
        'й' => 'q', 'ц' => 'w', 'у' => 'e', 'к' => 'r', 'е' => 't', 'н' => 'y', 'г' => 'u', 'ш' => 'i', 'щ' => 'o', 'з' => 'p',
        'ф' => 'a', 'ы' => 's', 'в' => 'd', 'а' => 'f', 'п' => 'g', 'р' => 'h', 'о' => 'j', 'л' => 'k', 'д' => 'l',
        'я' => 'z', 'ч' => 'x', 'с' => 'c', 'м' => 'v', 'и' => 'b', 'т' => 'n', 'ь' => 'm',
    ];

    /** Латинские буквы с диакритикой → базовые (для правил произношения). */
    private const DIACRITICS = [
        'ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];

    /** Кириллица → латиница (распространённая «паспортная» транслитерация). */
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
        'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** Латиница → кириллица: сочетания (длинные первыми), затем одиночные буквы. */
    private const REVERSE = [
        'sch' => 'щ', 'shch' => 'щ', 'zh' => 'ж', 'kh' => 'х', 'ch' => 'ч', 'sh' => 'ш', 'ts' => 'ц', 'yu' => 'ю', 'ya' => 'я', 'yo' => 'е',
        'ph' => 'ф', 'ck' => 'к', 'oo' => 'у', 'ee' => 'и',
        'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'й', 'k' => 'к',
        'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в',
        'w' => 'в', 'x' => 'кс', 'y' => 'ы', 'z' => 'з',
    ];

    /**
     * Слово, набранное в другой раскладке («ыфьыгтп» → «samsung»); null, если слово не состоит целиком из букв одной раскладки.
     */
    public static function layoutSwitch(string $word): ?string
    {
        $flip = array_flip(self::LAYOUT);
        $map = preg_match('/^[а-я]+$/u', $word) ? self::LAYOUT : (preg_match('/^[a-z]+$/', $word) ? $flip : null);

        if ($map === null) {
            return null;
        }

        $out = '';

        foreach (mb_str_split($word) as $char) {
            if (!isset($map[$char])) {
                return null;
            }

            $out .= $map[$char];
        }

        return $out;
    }

    /** Транслитерация слова в другое письмо («самсунг» → «samsung», «parma» → «парма»); null — слово смешанное или без букв. */
    public static function transliterate(string $word): ?string
    {
        if (preg_match('/^[а-яё]+$/u', $word)) {
            return strtr(str_replace('ё', 'е', $word), self::TRANSLIT);
        }

        if (preg_match('/^[a-z]+$/', $word)) {
            // Длинные сочетания (sch, shch, zh…) заменяются раньше одиночных букв: strtr берёт самое длинное совпадение.
            return strtr($word, self::REVERSE);
        }

        return null;
    }

    /**
     * Запасные написания слова «на слух», без словарей: как это слово обычно записывают в другом письме. Кириллица → латиница:
     * «ай» → ay/ai/i (в начале слова), «ф» → f/ph, «в» → v/w, «ей» → ey/ei/ay, «х» → h/kh и т. п. («айфон» → iphon, «хуавей» → huawei).
     * Латиница → кириллица: ph → ф, i → и/ай, конечная e не читается, c → к/с, w → в, y → ы/и/й и т. п. («iphone» → айфон, «chanel» → шанель).
     * Это правила произношения, а не список слов: они подходят любым товарам (духи, арматура, запчасти), но не знают исключений,
     * поэтому варианты идут после точной транслитерации, их число ограничено, а совпадение ищется по началу слова.
     *
     * @return  string[]
     */
    public static function phoneticVariants(string $word, int $limit = 10): array
    {
        // Диакритика («kärcher», «café») читается как базовая буква: правила ниже знают только a–z.
        $word = strtr(str_replace('ё', 'е', $word), self::DIACRITICS);

        if (mb_strlen($word) < 3) {
            return [];
        }

        if (preg_match('/^[а-я]+$/u', $word)) {
            $rules = [
                'ай' => ['ay', 'ai', 'i', 'y'], 'ей' => ['ey', 'ei', 'ay'], 'ой' => ['oy', 'oi'], 'уй' => ['uy', 'ui'],
                'кс' => ['ks', 'x'], 'дж' => ['dzh', 'j'], 'ф' => ['f', 'ph'], 'к' => ['k', 'c'], 'в' => ['v', 'w'], 'х' => ['h', 'kh'], 'ц' => ['ts', 'c'],
                'и' => ['i', 'y'], 'у' => ['u', 'oo'], 'ю' => ['yu', 'u'], 'я' => ['ya', 'ia'], 'э' => ['e', 'a'], 'е' => ['e'], 'ж' => ['zh', 'j'], 'ч' => ['ch'], 'ш' => ['sh', 'ch'],
                'щ' => ['sch', 'sh'], 'ы' => ['y', 'i'], 'ъ' => [''], 'ь' => [''],
                'а' => ['a'], 'б' => ['b'], 'г' => ['g'], 'д' => ['d'], 'з' => ['z'], 'й' => ['y'], 'л' => ['l'], 'м' => ['m'], 'н' => ['n'], 'о' => ['o'], 'п' => ['p'], 'р' => ['r'], 'с' => ['s', 'c'], 'т' => ['t'],
            ];
            $pure = self::transliterate($word);
        } elseif (preg_match('/^[a-z]+$/', $word)) {
            $rules = [
                'sch' => ['щ', 'ш'], 'ph' => ['ф'], 'ck' => ['к'], 'kh' => ['х'], 'ch' => ['ч', 'ш', 'к', 'х'], 'sh' => ['ш'], 'th' => ['з', 'т'], 'zh' => ['ж'], 'ts' => ['ц'],
                'ee' => ['и'], 'oo' => ['у'], 'ou' => ['у', 'ау'], 'ea' => ['и'], 'ai' => ['ай', 'эй'], 'ay' => ['ей', 'ай'], 'ei' => ['ей', 'ай'], 'ey' => ['ей'], 'oy' => ['ой'], 'qu' => ['кв'],
                'a' => ['а', 'э'], 'b' => ['б'], 'c' => ['к', 'с', 'ц'], 'd' => ['д'], 'e' => ['е', 'э'], 'f' => ['ф'], 'g' => ['г', 'дж'], 'h' => ['х', ''], 'i' => ['и', 'ай'], 'j' => ['дж', 'й', 'ж'],
                'k' => ['к'], 'l' => ['л'], 'm' => ['м'], 'n' => ['н'], 'o' => ['о', 'оу'], 'p' => ['п'], 'q' => ['к'], 'r' => ['р'], 's' => ['с', 'з'], 't' => ['т'], 'u' => ['у', 'а', 'ю'],
                'v' => ['в'], 'w' => ['в', 'у'], 'x' => ['кс', 'с'], 'y' => ['и', 'ай', 'ы', 'й'], 'z' => ['з'],
            ];
            $pure = self::transliterate($word);
        } else {
            return [];
        }

        $tokens = [];
        $chars = mb_str_split($word);
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $matched = false;

            foreach ([3, 2, 1] as $size) {
                $piece = implode('', array_slice($chars, $i, $size));

                if (mb_strlen($piece) === $size && isset($rules[$piece])) {
                    $alts = $rules[$piece];

                    // Конечная «e» после согласной не читается: «phone» → фон (вариант без неё первым).
                    if ($piece === 'e' && $i === $count - 1 && $i > 0) {
                        $alts = ['', 'е'];
                    }

                    $tokens[] = ['alts' => $alts, 'start' => $i === 0];
                    $i += $size - 1;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $tokens[] = ['alts' => [$chars[$i]], 'start' => $i === 0];
            }
        }

        // Перебор сочетаний: чем меньше «нестандартных» вариантов выбрано, тем раньше; число комбинаций ограничено.
        $combos = [['', 0]];

        foreach ($tokens as $token) {
            $next = [];

            foreach ($combos as [$prefix, $cost]) {
                foreach ($token['alts'] as $rank => $alt) {
                    if (str_starts_with($alt, '^')) {
                        if (!$token['start']) {
                            continue;
                        }

                        $alt = substr($alt, 1);
                    }

                    $next[] = [$prefix . $alt, $cost + $rank];
                }
            }

            usort($next, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
            $combos = array_slice($next, 0, 60);
        }

        $found = [];
        $short = 0;

        foreach ($combos as [$candidate]) {
            $length = mb_strlen($candidate);

            if ($length < 3 || $candidate === $pure || $candidate === $word || in_array($candidate, $found, true)) {
                continue;
            }

            // Трёхбуквенное начало совпадает со многими словами каталога (дорогой широкий поиск): таких вариантов не больше двух.
            if ($length === 3 && ++$short > 2) {
                continue;
            }

            $found[] = $candidate;
        }

        return array_slice($found, 0, $limit);
    }

    /**
     * Разбор правил синонимов запроса: по строке «слово = вариант, вариант». Слева одно слово, справа — слова (нормализованные).
     *
     * @return  array<string, string[]>
     */
    public static function parseSynonyms(string $rules): array
    {
        $map = [];

        foreach (preg_split('/\R/u', $rules) ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$left, $right] = explode('=', $line, 2);
            $key = self::normalize($left);
            $words = self::terms(str_replace(',', ' ', $right), 10);

            if ($key !== '' && !str_contains($key, ' ') && $words) {
                $map[$key] = array_values(array_unique(array_merge($map[$key] ?? [], $words)));
            }
        }

        return $map;
    }

    /**
     * Варианты слова запроса: само слово, синонимы (если заданы), другая раскладка и транслитерация (если включены); не больше $max.
     *
     * @param   array<string, string[]>  $synonyms
     *
     * @return  string[]
     */
    public static function variants(string $word, array $synonyms = [], bool $translit = true, int $max = 14): array
    {
        $variants = [$word];

        foreach ($synonyms[$word] ?? [] as $synonym) {
            $variants[] = $synonym;
        }


        if ($translit) {
            $latin = self::transliterate($word);

            foreach ([self::layoutSwitch($word), $latin] as $other) {
                if ($other !== null && $other !== '') {
                    $variants[] = $other;
                }
            }

            // Латинские написания, которые транслитерация даёт иначе: «кс» читается как x («галакси» → galaxy, «ксиаоми» → xiaomi),
            // конечная «и» — как y.
            if ($latin !== null && preg_match('/^[а-яё]+$/u', $word)) {
                $x = str_replace('ks', 'x', $latin);
                $extra = [$x];

                if (str_ends_with($x, 'i')) {
                    $extra[] = substr($x, 0, -1) . 'y';
                }

                if (str_ends_with($latin, 'i')) {
                    $extra[] = substr($latin, 0, -1) . 'y';
                }

                foreach ($extra as $other) {
                    $variants[] = $other;
                }

            }

            // Запись «на слух» (правила произношения без словарей): «айфон» → iphon, «хуавей» → huawei, «iphone» → айфон.
            foreach (self::phoneticVariants($word) as $other) {
                $variants[] = $other;
            }
        }

        return array_slice(array_values(array_unique($variants)), 0, $max);
    }
}
