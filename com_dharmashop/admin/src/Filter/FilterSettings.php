<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Filter;
\defined('_JEXEC') or die;

/** Shared validation for native field settings; unknown settings are never persisted. */
final class FilterSettings
{
    public static function normalize(array $settings): array
    {
        $result = [];
        foreach (['logic'=>['or','and'], 'control'=>['auto','checkboxes','select','radio','buttons','swatches','images'], 'sort'=>['field','alpha','count']] as $key=>$allowed) {
            $value = (string) ($settings[$key] ?? $allowed[0]);
            if (!in_array($value, $allowed, true)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID'); }
            $result[$key] = $value;
        }
        $result['limit'] = max(0, min(100, (int) ($settings['limit'] ?? 0)));
        $result['search'] = (int) (bool) ($settings['search'] ?? 1);
        $result['expanded'] = (int) (bool) ($settings['expanded'] ?? 1);
        return $result;
    }
}
