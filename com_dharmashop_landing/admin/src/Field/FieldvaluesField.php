<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinitionLoader;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterField;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Searchable, native multi-select of option-valued product fields. */
final class FieldvaluesField extends ListField
{
    protected $type = 'Fieldvalues';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        Factory::getApplication()->bootComponent('com_dharmashop');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $definition = (new FilterDefinitionLoader($db))->forCategory(0, FilterDefinitionLoader::currentLevels());
        foreach ($definition->fields as $field) {
            if ($field->kind !== FilterField::OPTION) { continue; }
            foreach ($field->options as $option) {
                $options[] = HTMLHelper::_('select.option', $field->alias . ':' . $option->alias, $field->title . ' — ' . $option->title);
            }
        }
        return $options;
    }
}
