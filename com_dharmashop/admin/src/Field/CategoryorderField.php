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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Нативные поля выбора порядка товара в каждой категории из витринного индекса. */
class CategoryorderField extends FormField
{
    protected $type = 'Categoryorder';

    protected function getInput(): string
    {
        $rows = is_array($this->value) ? $this->value : [];
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (!$rows) {
            return '<p class="text-muted">' . Text::_('COM_DHARMASHOP_ORDER_EMPTY') . '</p>';
        }

        $html = '<div class="table-responsive"><table class="table table-striped table-bordered align-middle">'
            . '<thead><tr><th scope="col">' . Text::_('COM_DHARMASHOP_ORDER_CATEGORY') . '</th>'
            . '<th scope="col">' . Text::_('COM_DHARMASHOP_ORDER_MODE') . '</th>'
            . '<th scope="col">' . Text::_('COM_DHARMASHOP_ORDER_POSITION') . '</th></tr></thead><tbody>';

        foreach ($rows as $catid => $row) {
            $row = (array) $row;
            $catid = (int) ($row['catid'] ?? $catid);
            $action = (string) ($row['action'] ?? 'manual');
            $sortOrder = (int) ($row['sort_order'] ?? 0);
            $idPrefix = $this->id . '_' . $catid;
            $html .= '<tr><th scope="row">' . $escape((string) ($row['title'] ?? '')) . '</th><td>'
                . '<div class="btn-group" role="group" aria-label="' . $escape(Text::_('COM_DHARMASHOP_ORDER_MODE')) . '">';

            foreach ([
                'first' => 'COM_DHARMASHOP_ORDER_FIRST',
                'last' => 'COM_DHARMASHOP_ORDER_LAST',
                'manual' => 'COM_DHARMASHOP_ORDER_MANUAL',
            ] as $value => $label) {
                $id = $idPrefix . '_' . $value;
                $checked = $action === $value ? ' checked' : '';
                $html .= '<input class="btn-check" type="radio" autocomplete="off" name="' . $escape($this->name . '[' . $catid . '][action]')
                    . '" id="' . $escape($id) . '" value="' . $value . '"' . $checked . '>'
                    . '<label class="btn btn-outline-primary" for="' . $escape($id) . '">' . Text::_($label) . '</label>';
            }

            $html .= '</div></td><td><input class="form-control" type="number" step="1" min="-2147483648" max="2147483647" name="'
                . $escape($this->name . '[' . $catid . '][sort_order]') . '" value="' . $sortOrder
                . '" aria-label="' . $escape(Text::sprintf('COM_DHARMASHOP_ORDER_POSITION_FOR', (string) ($row['title'] ?? ''))) . '"></td></tr>';
        }

        return $html . '</tbody></table></div>';
    }
}
