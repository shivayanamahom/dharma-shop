<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Выбор одного бейджа магазина из списка опубликованных. */
class BadgeField extends ListField
{
    protected $type = 'Badge';

    protected function getOptions(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $extension = CategoryTypes::BADGE;
        $rows = $db->setQuery(
            $db->createQuery()->select($db->quoteName(['id', 'title']))->from($db->quoteName('#__categories'))
                ->where('extension = :extension')->where('published = 1')->order('lft')->bind(':extension', $extension)
        )->loadObjectList();
        $options = [HTMLHelper::_('select.option', '0', '—')];

        foreach ($rows as $row) {
            $options[] = HTMLHelper::_('select.option', (string) $row->id, $row->title);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
