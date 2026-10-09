<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopRelated\Support;
\defined('_JEXEC') or die;
/** Структура сохраняемого блока; alias не зависит от заголовка и порядка строки subform. */
final class Blocks
{
    public const POSITIONS = ['beforeTitle','afterTitle','beforeContent','afterContent','beforeGallery','afterGallery',
        'beforeIntro','afterIntro','beforeIntroFields','afterIntroFields','beforeBuy','afterBuy','beforePrice','afterPrice',
        'beforeActions','afterActions','beforeTabs','afterTabs','beforeDescription','afterDescription','tabs'];
    public static function ids(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $values), static fn (int $id): bool => $id > 0)));
    }
    /** Пустой выбор означает основную категорию; специальный пункт можно сочетать с явными ID. */
    public static function categories(mixed $values): array
    {
        $ids = self::ids($values);
        if (!$ids || in_array('current', (array) $values, true)) { array_unshift($ids, 'current'); }

        return $ids;
    }
    public static function normalize(mixed $rows, bool $saving = false): array
    {
        if (is_string($rows)) { $rows = json_decode($rows, true) ?: []; }
        $result = [];
        $seen = [];
        foreach (array_slice((array) $rows, 0, 20) as $row) {
            $row = (array) $row;
            $alias = (string) ($row['alias'] ?? '');
            if (!preg_match('/^block_[a-f0-9]{16}$/D', $alias) || isset($seen[$alias])) {
                if (!$saving) { continue; }
                $alias = 'block_' . bin2hex(random_bytes(8));
            }
            $seen[$alias] = true;
            $block = ['alias' => $alias, 'title' => trim(strip_tags((string) ($row['title'] ?? ''))),
                'position' => in_array($row['position'] ?? '', self::POSITIONS, true) ? $row['position'] : '',
                'mode' => in_array($row['mode'] ?? '', ['manual','categories','fields'], true) ? $row['mode'] : 'manual',
                'reciprocal' => ($row['mode'] ?? 'manual') === 'manual' && (int) ($row['reciprocal'] ?? 0) === 1 ? 1 : 0,
                'categories' => self::categories($row['categories'] ?? []), 'fields' => self::ids($row['fields'] ?? []),
                'fields_operator' => ($row['fields_operator'] ?? 'and') === 'or' ? 'or' : 'and',
                'limit' => max(1, min(100, (int) ($row['limit'] ?? 4))),
                'layout' => in_array($row['layout'] ?? '', ['grid','list','slider'], true) ? $row['layout'] : 'grid',
                'columns' => max(1, min(6, (int) ($row['columns'] ?? 3))),
                'slider_gap' => max(0, min(64, (int) ($row['slider_gap'] ?? 16))),
                'slider_speed' => max(0, min(3000, (int) ($row['slider_speed'] ?? 400))),
                'slider_delay' => max(1000, min(60000, (int) ($row['slider_delay'] ?? 5000))),
                'component_layouts' => (int) ($row['component_layouts'] ?? 1) === 1 ? 1 : 0];
            foreach (['xs'=>1, 'sm'=>2, 'md'=>2, 'lg'=>3, 'xl'=>4, 'xxl'=>4] as $breakpoint => $default) {
                $block['slider_columns_' . $breakpoint] = max(1, min(6, (int) ($row['slider_columns_' . $breakpoint] ?? $default)));
            }
            foreach (['arrows'=>1, 'pagination'=>1, 'loop'=>0, 'autoplay'=>0] as $name => $default) {
                $block['slider_' . $name] = (int) ($row['slider_' . $name] ?? $default) === 1 ? 1 : 0;
            }
            $result[] = $block;
        }
        return $result;
    }
}
