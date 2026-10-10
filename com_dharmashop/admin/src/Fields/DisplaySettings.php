<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Fields;

\defined('_JEXEC') or die;

/** Common display configuration belongs to the component, not a type provider. */
final class DisplaySettings
{
    public const FORMATS = ['comma', 'list', 'image', 'image_title'];

    public static function normalize(array $settings, string $type): array
    {
        $result = [];
        foreach (['card', 'page'] as $context) {
            $locked = $type === 'editor' && $context === 'card';
            $result['load_' . $context] = $locked ? 0 : (int) (bool) ($settings['load_' . $context] ?? 1);
            $format = $locked ? 'comma' : (string) ($type === 'color'
                ? ($settings['color_format_' . $context] ?? $settings['format_' . $context] ?? 'image_title')
                : ($settings['format_' . $context] ?? 'comma'));
            if (!in_array($format, self::FORMATS, true)) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
            }
            $result['format_' . $context] = $format;
            if ($type === 'color') { $result['color_format_' . $context] = $format; }
            $result['show_title_' . $context] = (int) (bool) ($settings['show_title_' . $context] ?? 1);
        }
        $result['show_intro'] = (int) (bool) ($settings['show_intro'] ?? 0);
        $result['variation'] = in_array($type, ['list', 'color'], true) ? (int) (bool) ($settings['variation'] ?? 0) : 0;
        if ($result['variation'] && $type === 'list' && ((int) ($settings['list_multiple'] ?? 0) || (int) ($settings['multiple'] ?? 0))) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VARIATION_SINGLE_ONLY');
        }
        $display = (string) ($type === 'color' ? ($settings['color_group_card_display'] ?? $settings['group_card_display'] ?? 'off') : ($settings['group_card_display'] ?? 'off'));
        $format = (string) ($settings['group_card_format'] ?? 'image_title');
        if (!in_array($display, $type === 'color' ? ['off', 'select', 'radio', 'color'] : ['off', 'select', 'radio'], true)
            || !in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
        }
        $result['group_card_show'] = (int) (bool) ($settings['group_card_show'] ?? 1);
        $result['group_card_title'] = (int) (bool) ($settings['group_card_title'] ?? 1);
        $result['group_card_format'] = $format;
        $result['group_card_display'] = $display;
        if ($type === 'color') { $result['color_group_card_display'] = $display; }
        $productDisplay = (string) ($type === 'color' ? ($settings['color_product_choice_display'] ?? $settings['product_choice_display'] ?? 'color') : ($settings['product_choice_display'] ?? 'select'));
        $productFormat = (string) ($settings['product_choice_format'] ?? 'image_title');
        if (!in_array($productDisplay, $type === 'color' ? ['off', 'select', 'radio', 'color'] : ['off', 'select', 'radio'], true)
            || !in_array($productFormat, ['image', 'image_title'], true)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
        }
        $result['product_choice_display'] = $productDisplay;
        $result['product_choice_title'] = (int) (bool) ($settings['product_choice_title'] ?? 1);
        $result['product_choice_format'] = $productFormat;
        if ($type === 'color') { $result['color_product_choice_display'] = $productDisplay; }
        return $result;
    }
}
