<?php
/** Проверки чистых функций защиты от дублей адресов (C96); Joomla и сайт не нужны. */
declare(strict_types=1);

define('_JEXEC', true);
require dirname(__DIR__) . '/com_dharmashop/site/src/Seo/DuplicateGuard.php';

use Dharma\Component\DharmaShop\Site\Seo\DuplicateGuard as G;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
    echo "PASS {$message}\n";
};

$tolerated = G::patterns(G::TOLERATED_DEFAULT);
$own = ['Itemid', 'start', 'limitstart', 'sort', 'product_view', 'filter', 'filter_module', 'q'];
$clean = static fn (string $q): ?string => G::clean($q, $own, $tolerated);

// Шаблоны
$assert(G::patterns("UTM_*\n\n  yclid \n*\n**\n") === ['utm_*', 'yclid'], 'список: регистр, пробелы, пустые строки и «*» отбрасываются');
$assert(G::patterns("a\na\r\nb") === ['a', 'b'], 'список: повторы убираются, CRLF понимается');
$assert(G::matches('utm_source', ['utm_*']) && G::matches('UTM_Source', ['utm_*']), 'имя*: начинается, регистр не важен');
$assert(G::matches('xyclid', ['*yclid']) && !G::matches('yclidx', ['*yclid']), '*имя: заканчивается');
$assert(G::matches('a_ym_b', ['*_ym_*']) && !G::matches('abc', ['*_ym_*']), '*имя*: содержит');
$assert(G::matches('gclid', ['gclid']) && !G::matches('gclid2', ['gclid']) && !G::matches('xgclid', ['gclid']), 'имя: только точное совпадение');
$assert(!G::matches('anything', ['*', '**']), 'шаблон из одних звёздочек ничего не пропускает');

// Запрос
$assert($clean('sort=price_asc&start=24') === null, 'свои параметры страницы не меняются');
$assert($clean('utm_source=x&utm_medium=y&yclid=1') === null, 'метки из списка допустимы');
$assert($clean('foo=1') === '', 'неизвестный параметр убирается');
$assert($clean('sort=price_asc&foo=1&start=24') === 'sort=price_asc&start=24', 'остальные части и их порядок сохраняются');
$assert($clean('utm_source=a%20b&foo=1') === 'utm_source=a%20b', 'кодирование оставшегося не меняется');
$assert($clean('sort=') === '' && $clean('q=%20') === '', 'пустое значение скалярного параметра убирается');
$assert($clean('Sort=price_asc') === '', 'собственные имена точные: «Sort» — не «sort»');
$assert($clean('UTM_Source=1') === null, 'метки сравниваются без учёта регистра');
$assert($clean('Itemid=101&foo=1') === 'Itemid=101', 'Itemid сохраняется');
$assert($clean('start=0') === '' && $clean('limitstart=0&sort=new') === 'sort=new' && $clean('start=0&foo=1') === '', 'первая страница: start=0 убирается сразу вместе с лишним');
$assert($clean('start=24') === null && $clean('limitstart=5') === null, 'start с номером страницы сохраняется');
$assert($clean('limit=5') === '', 'limit не параметр страницы каталога (нужен подсказкам поиска): это дубль');

// Массивы фильтра остаются нетронутыми
$filter = 'filter%5Bf%5D%5Bcolor%5D%5B%5D=red&filter%5Bf%5D%5Bcolor%5D%5B%5D=blue&filter[price][min]=';
$assert($clean($filter) === null, 'массив filter[...] (в том числе пустые части) не пересобирается');
$assert($clean($filter . '&foo=1') === $filter, 'массив сохраняется как есть при удалении постороннего');
$assert($clean('other[a]=1') === '', 'неизвестный массив убирается');

// Прочее
$assert($clean('&&foo=1&') === '', 'пустые части игнорируются');
$assert($clean('=1') === '', 'часть без имени убирается');
$assert($clean('foo') === '' && $clean('start') === '', 'имя без значения: чужое убирается, своё без значения — тоже (пусто)');

// Сходимость: результат не требует второго редиректа
foreach (['foo=1&sort=price_asc', 'sort=&q=a&bar[]=1', 'utm_source=x&foo=1&filter[f][a][]=1'] as $q) {
    $once = $clean($q);
    $assert($once === null || $clean($once) === null, 'после редиректа второй не нужен: ' . $q);
}

echo "OK: {$checks}\n";
