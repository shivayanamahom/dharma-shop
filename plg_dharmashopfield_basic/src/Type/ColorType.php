<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;

/** Named, single-choice swatches use the same indexed option IDs as a list. */
final class ColorType extends BaseType
{
    public function id(): string { return 'color'; }
    public function store(): string { return 'option'; }
    public function normalizeSettings(array $settings): array
    {
        $result = ['color_multiple' => 0];
        foreach (['admin', 'site', 'filter'] as $context) {
            $key = 'color_display_' . $context;
            $value = $settings[$key] ?? 'radio';
            if (!is_scalar($value) || !in_array((string) $value, ['select', 'radio'], true)) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_SETTINGS_INVALID');
            }
            $result[$key] = (string) $value;
        }
        return $result;
    }

    public function input(object $field, array $options): \SimpleXMLElement
    {
        $radio = ($field->settings['color_display_admin'] ?? 'radio') !== 'select';
        $xml = new \SimpleXMLElement('<field />');
        $xml['name'] = 'f_' . (int) $field->id;
        $xml['label'] = (string) $field->title;
        $xml['translateLabel'] = 'false';
        $xml['translateOptions'] = 'false';
        $xml['required'] = (int) $field->required === 1 ? 'true' : 'false';
        $xml['filter'] = 'raw';
        $xml['validate'] = 'options';
        $xml['type'] = $radio ? 'colorradio' : 'list';
        $xml['addfieldprefix'] = 'Dharma\Component\DharmaShop\Administrator\Field';
        if (!$radio) { $xml['layout'] = 'joomla.form.field.list-fancy-select'; }
        if ((int) $field->required !== 1 || !$radio) {
            $xml->addChild('option', 'COM_DHARMASHOP_FIELD_EMPTY')->addAttribute('value', '');
        }
        foreach ($options as $option) {
            $node = $xml->addChild('option');
            $node[0] = (string) $option->title;
            $node['value'] = (string) $option->id;
            $node['color'] = (string) ($option->color ?? '');
            $node['image'] = (string) ($option->image ?? '');
        }
        return $xml;
    }
}
