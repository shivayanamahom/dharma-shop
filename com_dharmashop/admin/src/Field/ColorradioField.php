<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Form\Field\RadioField;

\defined('_JEXEC') or die;

/** Native Joomla radio field, with swatch metadata supplied by the value provider. No SQL or JS. */
final class ColorradioField extends RadioField
{
    protected $type = 'Colorradio';
    protected $layout = 'field.colorradio';

    protected function getLayoutPaths()
    {
        return array_merge(parent::getLayoutPaths(), [JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts']);
    }

    protected function getOptions()
    {
        $options = parent::getOptions();
        $swatches = [];
        foreach ($this->element->option as $node) {
            $swatches[(string) $node['value']] = ['color' => (string) $node['color'], 'image' => (string) $node['image']];
        }
        foreach ($options as $option) {
            $option->color = $swatches[(string) $option->value]['color'] ?? '';
            $option->image = $swatches[(string) $option->value]['image'] ?? '';
        }
        return $options;
    }
}
