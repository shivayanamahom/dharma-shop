<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopProducts\Site\Field;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinitionLoader;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterField;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

\defined('_JEXEC') or die;

/** Native selector for numeric and range fields in repeatable module conditions. */
final class RangefieldsField extends ListField
{
    protected $type = 'Rangefields';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        Factory::getApplication()->bootComponent('com_dharmashop');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $definition = (new FilterDefinitionLoader($db))->forCategory(0, FilterDefinitionLoader::currentLevels());
        foreach ($definition->fields as $field) {
            if ($field->kind !== FilterField::OPTION) { $options[] = HTMLHelper::_('select.option', $field->alias, $field->title . ($field->unit !== '' ? ', ' . $field->unit : '')); }
        }
        return $options;
    }
}
