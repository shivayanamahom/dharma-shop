<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
use Dharma\Component\DharmaShop\Administrator\Fields\ValueTypeInterface;
\defined('_JEXEC') or die;
abstract class BaseType implements ValueTypeInterface
{
    use NativeValue;
    public function label(): string { return 'COM_DHARMASHOP_FIELD_TYPE_' . strtoupper($this->id()); }
    public function settingsPath(): string { return dirname(__DIR__, 2) . '/forms/' . $this->id() . '/settings.xml'; }
    public function normalizeSettings(array $settings): array
    {
        $form = simplexml_load_file($this->settingsPath());
        $result = [];
        foreach ($form->xpath('//field') as $field) {
            $key = (string) $field['name'];
            if (array_key_exists($key, $settings) && is_scalar($settings[$key])) {
                $result[$key] = (string) $settings[$key];
            }
        }
        foreach (['number_precision', 'range_precision'] as $key) {
            if (isset($result[$key]) && $result[$key] !== '' && ((int) $result[$key] < 0 || (int) $result[$key] > 6)) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
            }
        }
        if (isset($result['text_maxlength']) && $result['text_maxlength'] !== ''
            && ((int) $result['text_maxlength'] < 1 || (int) $result['text_maxlength'] > 190)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
        }
        return $result;
    }
    protected function validateNumberSettings(array $settings, string $prefix): array
    {
        foreach (['min', 'max', 'step'] as $suffix) {
            $key = $prefix . '_' . $suffix;
            $value = $settings[$key] ?? '';
            if ($value !== '' && (!is_numeric($value) || !is_finite((float) $value))) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_NUMERIC_INVALID');
            }
        }
        if (($settings[$prefix . '_min'] ?? '') !== '' && ($settings[$prefix . '_max'] ?? '') !== ''
            && (float) $settings[$prefix . '_min'] > (float) $settings[$prefix . '_max']) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_BOUNDS_INVALID');
        }
        if (($settings[$prefix . '_step'] ?? '') !== '' && (float) $settings[$prefix . '_step'] <= 0) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_STEP_INVALID');
        }
        return $settings;
    }
}
