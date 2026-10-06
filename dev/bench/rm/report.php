<?php
// Dev-only: сводка сравнения RadicalMart и Dharma Shop → docs/BENCHMARKS_RM.md (php dev/bench/rm/report.php).
// Входы: dev/bench/rm/results/<N>.json (RadicalMart по HTTP + журнал запросов БД) и dev/bench/results/<N>.json (наша схема, только БД).
$rmDir = __DIR__ . '/results';
$ourDir = dirname(__DIR__) . '/results';
$sizes = [10000, 50000];

$load = static fn (string $f) => is_file($f) ? json_decode(file_get_contents($f), true) : null;
$rm = [];
$our = [];
foreach ($sizes as $n) {
    $rm[$n] = $load("$rmDir/$n.json");
    $our[$n] = $load("$ourDir/$n.json");
}
$rm0 = $load("$rmDir/114.json");

$find = static function (?array $data, string $needle): ?array {
    foreach ($data['results'] ?? [] as $r) {
        if (str_starts_with($r['name'], $needle)) {
            return $r;
        }
    }

    return null;
};

// [название у RadicalMart (начало), название у нас (начало)|null]
$map = [
    ['не-RM страница', null],
    ['главная', null],
    ['категория «Продукция» (корень), стр. 1', 'листинг root(1) / order'],
    ['корень, сортировка по цене', 'листинг root(1) / price_asc'],
    ['корень, сортировка по названию', 'листинг root(1) / title'],
    ['корень, сортировка по дате', 'листинг root(1) / new'],
    ['категория «Продукция», глубокая', 'листинг root(1) / order / середина каталога'],
    ['категория-узел', 'листинг L2(12) / order'],
    ['категория-лист', 'листинг leaf-hot(112) / order'],
    ['корень + фильтр по полю', 'листинг+1 фильтр root(1) / order'],
    ['карточка клонированного', 'карточка товара'],
];

$f = static fn ($v, int $d = 1) => $v === null ? '—' : number_format((float) $v, $d, ',', '');
$out = "<!-- Создаётся командой `php dev/bench/rm/report.php`; выводы и оговорки — в docs/BENCHMARKS_RM_NOTES.md. -->\n\n# RadicalMart и Dharma Shop: сравнение скорости\n\n";
$out .= "RadicalMart 3.0.27 замерялся как «чёрный ящик»: HTTP-ответ страницы (время до первого байта, медиана 9 прогонов) и журнал запросов MySQL на время одного обращения. Код RadicalMart не читался. Dharma Shop замерялся на уровне базы (веб-страниц ещё нет), поэтому сравнивается прежде всего **время БД** и число просмотренных строк.\n\n";
$out .= '- Исходный каталог RadicalMart: ' . ($rm0['products'] ?? '?') . ' товаров, ' . ($rm0['items'] ?? '?') . " строк в индексе списков; дальше он размножен клонированием существующих товаров (`dev/bench/rm/clone.php`), индекс пересобран штатными командами RadicalMart.\n";
$out .= "- «БД RM» — суммарное время всех запросов страницы по журналу MySQL (включая запросы Joomla); «Dharma БД» — время запросов нашего листинга (ключи страницы + карточки), без остального.\n\n";

foreach ($sizes as $n) {
    if (!$rm[$n]) {
        continue;
    }
    $out .= '## ' . number_format($n, 0, '', ' ') . " товаров\n\n";
    $out .= "| Сценарий | RM: ответ, мс | RM: БД, мс | RM: запросов | RM: строк просмотрено | Dharma: БД, мс |\n|---|---:|---:|---:|---:|---:|\n";
    foreach ($map as [$rmName, $ourName]) {
        $r = $find($rm[$n], $rmName);
        if (!$r) {
            continue;
        }
        $o = $ourName ? $find($our[$n], $ourName) : null;
        $out .= '| ' . $r['name'] . ' | ' . $f($r['ttfb_ms']) . ' | ' . $f($r['db_ms']) . ' | ' . $r['db_queries'] . ' | ' . number_format($r['rows_examined'], 0, '', ' ') . ' | ' . ($o ? $f($o['ms'], 2) : '—') . " |\n";
    }
    $out .= "\n";
}

if ($rm0) {
    $out .= "## Для сравнения: исходный каталог RadicalMart (" . $rm0['products'] . " товаров)\n\n| Сценарий | Ответ, мс | БД, мс | Запросов | Строк просмотрено |\n|---|---:|---:|---:|---:|\n";
    foreach ($map as [$rmName]) {
        $r = $find($rm0, $rmName);
        if ($r) {
            $out .= '| ' . $r['name'] . ' | ' . $f($r['ttfb_ms']) . ' | ' . $f($r['db_ms']) . ' | ' . $r['db_queries'] . ' | ' . number_format($r['rows_examined'], 0, '', ' ') . " |\n";
        }
    }
    $out .= "\n";
}

$out .= "## Самые тяжёлые запросы RadicalMart (по журналу MySQL)\n\n";
foreach (array_reverse($sizes) as $n) {
    if (!$rm[$n]) {
        continue;
    }
    $out .= '### ' . number_format($n, 0, '', ' ') . " товаров\n\n";
    foreach (['категория «Продукция» (корень), стр. 1', 'корень + фильтр по полю', 'не-RM страница'] as $needle) {
        $r = $find($rm[$n], $needle);
        if (!$r) {
            continue;
        }
        $out .= '**' . $r['name'] . "**\n\n";
        foreach ($r['top'] as $t) {
            $sql = preg_replace('/^\d{4}-\d\d-\d\dT[\d:.]+Z\s+/', '', $t['sql']);
            $out .= '- ' . $f($t['ms'], 1) . ' мс, просмотрено ' . number_format($t['ex'], 0, '', ' ') . ' строк: `' . str_replace('`', '', mb_substr($sql, 0, 160)) . "…`\n";
        }
        $out .= "\n";
    }
}
file_put_contents(dirname(__DIR__, 3) . '/docs/BENCHMARKS_RM.md', $out);
echo "docs/BENCHMARKS_RM.md written\n";
