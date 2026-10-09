<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Payment\TypeRegistry;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Список типов доставки из включённых плагинов группы dharmashoppayment. */
final class PaymenttypeField extends ListField
{
    protected $type = 'Paymenttype';

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        foreach (TypeRegistry::current()->all() as $id => $type) {
            $options[] = HTMLHelper::_('select.option', $id, Text::_($type->label()));
        }

        return $options;
    }
}
