<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общие параметры сетки Bootstrap для списков магазина. */
final class Grid
{
    private const BREAKPOINTS = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];

    public static function keys(string $prefix): array
    {
        return array_merge([$prefix . '_columns', $prefix . '_breakpoints'],
            array_map(static fn (string $size): string => $prefix . '_columns_' . $size, self::BREAKPOINTS));
    }

    public static function classes(Registry $params, string $prefix, string $gapClass = 'g-4'): string
    {
        $columns = self::columns($params->get($prefix . '_columns', 3));
        if ((int) $params->get($prefix . '_breakpoints', 0) !== 1) {
            return 'row row-cols-1 row-cols-md-' . min(2, $columns) . ' row-cols-lg-' . $columns . ' ' . $gapClass;
        }
        $defaults = ['xs' => 1, 'sm' => 1, 'md' => min(2, $columns), 'lg' => $columns, 'xl' => $columns, 'xxl' => $columns];
        $classes = ['row'];
        foreach (self::BREAKPOINTS as $size) {
            $value = $params->get($prefix . '_columns_' . $size);
            $number = self::columns($value === null || $value === '' ? $defaults[$size] : $value);
            $classes[] = 'row-cols-' . ($size === 'xs' ? '' : $size . '-') . $number;
        }
        $classes[] = $gapClass;

        return implode(' ', $classes);
    }

    private static function columns(mixed $value): int
    {
        return max(1, min(6, (int) $value));
    }
}
