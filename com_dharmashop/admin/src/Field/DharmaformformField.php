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

use Dharma\Component\DharmaForm\Site\Service\FormService;
use Dharma\Component\DharmaShop\Administrator\Support\QuickOrder;
use Joomla\CMS\Form\Field\ListField;

\defined('_JEXEC') or die;

/** Список опубликованных всплывающих форм Dharma Form (значение — код формы). Без компонента остаётся сохранённый или стандартный код. */
class DharmaformformField extends ListField
{
    protected $type = 'Dharmaformform';

    protected function getOptions(): array
    {
        $options = [];

        if (QuickOrder::installed()) {
            try {
                foreach (array_keys((new FormService())->triggers()) as $code) {
                    $options[(string) $code] = (string) $code;
                }
            } catch (\Throwable) {
                $options = [];
            }
        }

        // Сохранённый код показываем всегда: иначе сохранение страницы настроек молча заменило бы его первым пунктом списка.
        $current = (string) ($this->value !== '' && $this->value !== null ? $this->value : $this->default);

        if ($current !== '' && !isset($options[$current])) {
            $options[$current] = $current;
        }

        $out = [];

        foreach ($options as $value => $text) {
            $out[] = (object) ['value' => $value, 'text' => $text];
        }

        return array_merge(parent::getOptions(), $out);
    }
}
