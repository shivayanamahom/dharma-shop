<?php
// Dev-only: собирает docs/BENCHMARKS.md из dev/bench/results/*.json (php dev/bench/report.php).
$dir = __DIR__ . '/results';
$sizes = [100, 10000, 50000, 100000];
$data = [];
foreach ($sizes as $n) {
    $f = "$dir/$n.json";
    if (is_file($f)) {
        $data[$n] = json_decode(file_get_contents($f), true);
    }
}
$base = reset($data);
$out = "<!-- Файл создаётся командой `php dev/bench/report.php`; не правьте таблицу вручную. Выводы и оговорки — в docs/BENCHMARKS_NOTES.md. -->\n\n";
$out .= "# Dharma Shop — замеры скорости схемы\n\n";
$out .= '- Дата: ' . substr($base['date'], 0, 10) . ', MySQL ' . $base['mysql'] . ", Apple M4, 16 ГБ, Kettle (локально), `innodb_buffer_pool_size` = 128 МБ (по умолчанию).\n";
$out .= "- Данные синтетические (`dev/bench/bench.php generate N`): 19 полей, до 6 вариантов у 40 % товаров, дерево категорий 1→10→100→1000 листьев, 20 % товаров во второй категории.\n";
$out .= "- Время — медиана 9 прогонов после 2 прогревов, миллисекунды, **только база** (включая второй шаг «карточки по ключам»), без PHP-отрисовки.\n\n";
$out .= '| Сценарий | ' . implode(' | ', array_map(fn ($n) => number_format($n, 0, '', ' ') . ' товаров', array_keys($data))) . " | План на максимальном размере |\n";
$out .= '|---|' . str_repeat('---:|', count($data)) . "---|\n";
$last = end($data);
$byName = static function (array $d, string $name): ?array {
    foreach ($d['results'] as $r) {
        if ($r['name'] === $name) {
            return $r;
        }
    }

    return null;
};
foreach ($last['results'] as $row) {
    $cells = [];
    foreach ($data as $d) {
        $r = $byName($d, $row['name']);
        $cells[] = $r ? number_format($r['ms'], 2, ',', '') : '—';
    }
    $plan = $row['plan'] ?? [];
    $out .= '| ' . $row['name'] . ' | ' . implode(' | ', $cells) . ' | ' . ($plan ? implode(', ', array_keys($plan)) : '—') . " |\n";
}
$out .= "\n## Размеры таблиц (МБ) на максимальном размере\n\n| Таблица | МБ | Строк (оценка) |\n|---|---:|---:|\n";
foreach ($last['sizes'] as $s) {
    if ((float) $s['mb'] >= 1) {
        $out .= "| {$s['t']} | {$s['mb']} | {$s['r']} |\n";
    }
}
file_put_contents(dirname(__DIR__, 2) . '/docs/BENCHMARKS.md', $out);
echo "docs/BENCHMARKS.md written\n";
