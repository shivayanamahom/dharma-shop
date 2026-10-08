<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\Rule\SubformRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Нативная проверка строк конструктора плюс уникальность имён и пределы размера. */
class CheckoutdefinitionsRule extends SubformRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        // Form::validate() помещает данные в Registry: ассоциативные строки становятся stdClass.
        if ($value instanceof Registry) {
            $value = $value->toArray();
        } elseif ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if ($value === null || $value === []) {
            return true;
        }

        if (!is_array($value) || count($value) > 50) {
            return false;
        }

        foreach ($value as $key => $row) {
            if ($row instanceof Registry) {
                $row = $row->toArray();
            } elseif ($row instanceof \stdClass) {
                $row = get_object_vars($row);
            }

            if (!is_array($row)) {
                return false;
            }

            $value[$key] = $row;
        }

        $valid = parent::test($element, $value, $group, $input, $form);

        if ($valid instanceof \Exception) {
            throw $valid;
        }

        if ($valid !== true) {
            return false;
        }

        $names = [];

        foreach ($value as $row) {
            $name = (string) ($row['name'] ?? '');

            if (isset($names[$name])) {
                throw new \RuntimeException(Text::sprintf('COM_DHARMASHOP_CHECKOUT_NAME_DUPLICATE', $name));
            }

            $names[$name] = true;

            foreach (['label' => 150, 'hint' => 150, 'mask' => 64, 'text' => 2000, 'url' => 2048, 'link_label' => 150] as $key => $limit) {
                if (isset($row[$key]) && (!is_scalar($row[$key]) || mb_strlen((string) $row[$key]) > $limit)) {
                    throw new \RuntimeException(Text::sprintf('COM_DHARMASHOP_CHECKOUT_DEFINITION_TOO_LONG', $name, $limit));
                }
            }

            if (($row['type'] ?? '') === 'tel' && !str_contains((string) ($row['mask'] ?? ''), '#')) {
                throw new \RuntimeException(Text::sprintf('COM_DHARMASHOP_CHECKOUT_PHONE_MASK_INVALID', $name));
            }

            if (trim((string) ($row['label'] ?? $row['text'] ?? '')) === '') {
                throw new \RuntimeException(Text::sprintf('COM_DHARMASHOP_CHECKOUT_DEFINITION_EMPTY', $name));
            }
        }

        return true;
    }
}
