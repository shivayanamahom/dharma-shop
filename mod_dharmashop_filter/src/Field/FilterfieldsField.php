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

        foreach (['@price' => 'PRICE', '@brand' => 'BRAND', '@badge' => 'BADGE', '@cat' => 'CAT', '@stock' => 'STOCK'] as $key => $label) {
            $options[] = HTMLHelper::_('select.option', $key, Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_' . $label));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->select(['alias', 'title'])->from($db->quoteName('#__dharmashop_fields'))
            ->where('target = ' . $db->quote('product'))->where('state = 1')->where('filterable = 1')->order('ordering, id');

        foreach ((array) $db->setQuery($query)->loadObjectList() as $row) {
            $options[] = HTMLHelper::_('select.option', (string) $row->alias, $row->title . ' [' . $row->alias . ']');
        }

        return $options;
    }
}
