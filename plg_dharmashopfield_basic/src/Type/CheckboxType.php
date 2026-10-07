<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;
final class CheckboxType extends BaseType
{
    public function id(): string { return 'checkbox'; }
    public function store(): string { return 'option'; }

    public function normalizeSettings(array $settings): array
    {
        $result = parent::normalizeSettings($settings);
        $result['checkbox_mode'] = (string) ($result['checkbox_mode'] ?? 'single');
        $result['checkbox_default'] = (string) ($result['checkbox_default'] ?? '');
        $result['checkbox_default_option'] = trim(strip_tags((string) ($result['checkbox_default_option'] ?? '')));
        if (!in_array($result['checkbox_mode'], ['single', 'options'], true)
            || !in_array($result['checkbox_default'], ['', '0', '1'], true)
            || mb_strlen($result['checkbox_default_option']) > 255) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
        }
        // The component resolves the entered title against this field's own options.
        $result['checkbox_default_option_id'] = $result['checkbox_mode'] === 'options'
            ? max(0, (int) ($settings['checkbox_default_option_id'] ?? 0)) : 0;
        return $result;
    }
}
