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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Список способов оплаты из раздела «Оплата» (форма способа доставки: какая оплата доступна). */
final class PaymentselectField extends ListField
{
    protected $type = 'Paymentselect';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $rows = (array) $db->setQuery($db->createQuery()->select(['id', 'title', 'state'])->from($db->quoteName('#__dharmashop_payment_methods'))->order('ordering, id'))->loadObjectList();

        foreach ($rows as $row) {
            $options[] = HTMLHelper::_('select.option', (string) $row->id, $row->title . ((int) $row->state === 1 ? '' : ' (' . Text::_('JUNPUBLISHED') . ')'));
        }

        return $options;
    }
}
