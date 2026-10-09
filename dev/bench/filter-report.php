<?php
// Dev-only: собирает docs/BENCHMARKS_FILTER.md из dev/bench/results/filter-*.json (подключается командой `php dev/bench/filter.php report`).
$files = glob(__DIR__ . '/results/filter-*.json') ?: [];
$data = [];
foreach ($files as $f) {
    $j = json_decode((string) file_get_contents($f), true);
    if (is_array($j) && isset($j['n'])) {
        $data[(int) $j['n']] = $j;
    }
}
ksort($data);
$sizes = array_keys($data);
$md = "# Замеры фильтра (F0)\n\nСгенерировано `php dev/bench/filter.php report` из `dev/bench/results/filter-*.json`. Время БД через драйвер Joomla, медиана 7 прогонов после 2 прогревов, мс. "
    . "Данные синтетические (`dev/bench/bench.php`), `innodb_buffer_pool_size` = 128 МБ. План: `F` — filesort, `T` — временная таблица, `S:` — полное сканирование таблицы. Подробности и выводы — `docs/FILTER.md` §9.\n\n";
$md .= '| Размер каталога | ' . implode(' | ', array_map(static fn (int $n): string => number_format($n, 0, ',', ' '), $sizes)) . " |\n|---|" . str_repeat('---|', count($sizes)) . "\n";
foreach (['products' => 'товаров', 'field_values' => 'строк field_values', 'product_categories' => 'строк product_categories'] as $key => $label) {
    $md .= "| $label | " . implode(' | ', array_map(static fn (array $d): string => number_format((int) ($d['stats'][$key] ?? 0), 0, ',', ' '), $data)) . " |\n";
}
$md .= "\n";
$groups = ['forms' => '1. Формы условий (список, 24 позиции)', 'count' => '2. Число найденных с потолком 10 001', 'cov-index' => '3. С покрывающим индексом `(product_id, field_id, option_id, num)`',
    'facets' => '4. Значения и счётчики', 'precalc' => '5. Предрасчёт базового состояния'];
$short = static function (string $plan): string {
    $out = [];
    foreach (array_filter(explode(',', $plan)) as $p) {
        $out[] = $p === 'filesort' ? 'F' : ($p === 'temp' ? 'T' : 'S');
    }

    return implode('', $out);
};
foreach ($groups as $g => $title) {
    $names = [];
    foreach ($data as $d) {
        foreach ($d['rows'] as $r) {
            if ($r['group'] === $g) {
                $names[$r['name']] = true;
            }
        }
    }
    if (!$names) {
        continue;
    }
    $md .= "## $title\n\n| Замер | " . implode(' | ', array_map(static fn (int $n): string => number_format($n, 0, ',', ' '), $sizes)) . " |\n|---|" . str_repeat('---|', count($sizes)) . "\n";
    foreach (array_keys($names) as $name) {
        $cells = [];
        foreach ($data as $d) {
            $cell = '—';
            foreach ($d['rows'] as $r) {
                if ($r['group'] === $g && $r['name'] === $name) {
                    $cell = number_format((float) $r['ms'], 2, ',', '') . ($r['plan'] !== '' ? ' ' . $short($r['plan']) : '');
                }
            }
            $cells[] = $cell;
        }
        $md .= '| ' . $name . ' | ' . implode(' | ', $cells) . " |\n";
    }
    $md .= "\n";
}
$md .= "## Служебное\n\n";
foreach ($data as $n => $d) {
    $md .= "- $n: согласованность форм — " . (is_array($d['consistency'] ?? null) ? implode('; ', $d['consistency']) : ($d['consistency'] ?? '?'))
        . '; сборка индекса — ' . ($d['index_build_s'] ?? '—') . ' с; полная сборка предрасчёта — ' . ($d['facets_build_s'] ?? '—') . ' с, строк ' . ($d['facets_rows'] ?? '—')
        . ', строк на товар ' . ($d['facets_rows_per_product'] ?? '—') . "\n";
}
// Раунды 2 и 3 (максимальный размер): сравнение форм A (EXISTS), F (индекс сортировки, ранняя остановка), D (ведущая выборка).
foreach (['filter2' => '6. Раунд 2: форма по плотности условия (A — EXISTS, F — обход индекса сортировки с ранней остановкой, D — ведущая выборка)', 'filter3' => '7. Раунд 3: плотность условия, страница 51, число найденных (с покрывающим индексом)'] as $prefix => $title) {
    $fs = glob(__DIR__ . "/results/$prefix-*.json") ?: [];
    if (!$fs) {
        continue;
    }
    natsort($fs);
    $j = json_decode((string) file_get_contents(end($fs)), true);
    $md .= "## $title\n\nРазмер каталога: " . number_format((int) $j['n'], 0, ',', ' ') . ". Мс, медиана; план: F — filesort, T — временная таблица, S — полное сканирование.\n\n";
    $byBase = [];
    foreach ($j['rows'] as $r) {
        $parts = explode(' / ', $r['name']);
        $variant = array_pop($parts);
        $variant = preg_replace('/ \(.*\)$/u', '', $variant);
        $byBase[$r['group'] . ' · ' . implode(' / ', $parts)][$variant] = $r['ms'] . ($r['plan'] !== '' ? ' ' . $r['plan'] : '');
    }
    $cols = [];
    foreach ($byBase as $row) {
        foreach (array_keys($row) as $c) {
            $cols[$c] = true;
        }
    }
    $cols = array_keys($cols);
    $md .= '| Замер | ' . implode(' | ', $cols) . " |\n|---|" . str_repeat('---|', count($cols)) . "\n";
    foreach ($byBase as $name => $row) {
        $md .= "| $name | " . implode(' | ', array_map(static fn (string $c): string => str_replace('.', ',', $row[$c] ?? '—'), $cols)) . " |\n";
    }
    $md .= "\n";
    if (isset($j['table_before_mb'])) {
        $md .= '- `field_values`: данные ' . $j['table_before_mb']['data'] . ' МБ, индексы ' . $j['table_before_mb']['index'] . " МБ (до пробного индекса)\n\n";
    }
}
file_put_contents(dirname(__DIR__, 2) . '/docs/BENCHMARKS_FILTER.md', $md);
echo "docs/BENCHMARKS_FILTER.md\n";
