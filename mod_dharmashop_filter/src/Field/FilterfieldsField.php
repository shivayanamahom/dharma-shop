<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    mod_dharmashop_filter
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Module\DharmaShopFilter\Site\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Список для конфигурации фильтров модуля: системные фильтры (цена, бренд, бейджи, подкатегории, наличие) и поля товаров с включённым фильтром. */
final class FilterfieldsField extends ListField
{
    protected $type = 'Filterfields';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        Factory::getApplication()->getLanguage()->load('mod_dharmashop_filter', JPATH_SITE . '/modules/mod_dharmashop_filter');

        foreach (['@price' => 'PRICE', '@brand' => 'BRAND', '@badge' => 'BADGE', '@cat' => 'CAT', '@stock' => 'STOCK', '@sale' => 'SALE'] as $key => $label) {
            $options[] = HTMLHelper::_('select.option', $key, Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_' . $label));
        }

        Factory::getApplication()->bootComponent('com_dharmashop');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $definition = (new \Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinitionLoader($db))->forCategory(0, \Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinitionLoader::currentLevels());
        foreach ($definition->fields as $row) {
            $options[] = HTMLHelper::_('select.option', $row->alias, $row->title . ' [' . $row->alias . ']');
        }

        return $options;
    }
}
