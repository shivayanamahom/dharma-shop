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
            $format = $locked ? 'comma' : (string) ($settings['format_' . $context] ?? 'comma');
            if (!in_array($format, self::FORMATS, true)) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
            }
            $result['format_' . $context] = $format;
        }
        $result['variation'] = $type === 'list' ? (int) (bool) ($settings['variation'] ?? 0) : 0;
        return $result;
    }
}
