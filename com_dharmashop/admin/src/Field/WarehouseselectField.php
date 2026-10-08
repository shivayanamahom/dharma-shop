<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Выбор складов из справочника (одного или нескольких, атрибут multiple): названия с адресом; неопубликованные помечаются. */
final class WarehouseselectField extends ListField
{
    protected $type = 'Warehouseselect';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $rows = (array) $db->setQuery($db->createQuery()->select(['id', 'title', 'address', 'state'])->from($db->quoteName('#__dharmashop_warehouses'))->order('ordering, id'))->loadObjectList();

        foreach ($rows as $row) {
            $label = $row->title . ($row->address ? ' — ' . mb_substr(str_replace(["\r", "\n"], ' ', (string) $row->address), 0, 60) : '') . ((int) $row->state === 1 ? '' : ' (' . Text::_('JUNPUBLISHED') . ')');
            $options[] = HTMLHelper::_('select.option', (string) $row->id, $label);
        }

        return $options;
    }
}
