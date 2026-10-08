<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Form\Field\SubformField;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Штатный subform с заполнением пустых технических имён до проверки com_config. */
class CheckoutdefinitionsField extends SubformField
{
    protected $type = 'Checkoutdefinitions';

    public function filter($value, $group = null, ?Registry $input = null)
    {
        $rows = parent::filter($value, $group, $input);

        if (!is_array($rows)) {
            return $rows;
        }

        $used = [];

        // Сначала резервируем все заданные имена, включая строки ниже автоматически создаваемой.
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');

            if ($name !== '') {
                $used[$name] = true;
            }
        }

        foreach ($rows as $key => $row) {
            if (($row['type'] ?? '') === 'tel' && trim((string) ($row['mask'] ?? '')) === '') {
                $rows[$key]['mask'] = '+7 (###) ###-##-##';
            }

            if ((string) ($row['name'] ?? '') !== '') {
                continue;
            }

            $base = str_replace('-', '_', OutputFilter::stringURLSafe((string) ($row['label'] ?? $row['text'] ?? '')));

            if ($base === '') {
                $base = $this->fieldname === 'checkout_consents' ? 'consent' : 'field';
            } elseif (!preg_match('/^[a-z]/', $base)) {
                $base = 'field_' . $base;
            }

            $base = rtrim(substr($base, 0, 64), '_');
            $name = $base;
            $number = 2;

            while (isset($used[$name])) {
                $suffix = '_' . $number++;
                $name = rtrim(substr($base, 0, 64 - strlen($suffix)), '_') . $suffix;
            }

            $rows[$key]['name'] = $name;
            $used[$name] = true;
        }

        return $rows;
    }
}
