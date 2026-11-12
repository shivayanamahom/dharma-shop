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
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Состояние компонента Dharma Form для вкладки «Быстрый заказ»: нужен ли он, установлен ли, есть ли выбранная форма и привязывает ли она товар.
 * Штатное предупреждение Bootstrap со ссылками на установку; значения в настройки не сохраняются.
 */
class DharmaformstatusField extends FormField
{
    protected $type = 'Dharmaformstatus';

    public function renderField($options = [])
    {
        return $this->getInput();
    }

    protected function getInput(): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link   = '<a href="' . $escape(QuickOrder::SITE_URL) . '" target="_blank" rel="noopener noreferrer">Dharma Form</a>';

        if (!QuickOrder::installed()) {
            $install = '<a href="' . $escape(Route::_('index.php?option=com_installer&view=install', false)) . '">' . $escape(Text::_('COM_DHARMASHOP_QUICK_ORDER_STATUS_INSTALL_PAGE')) . '</a>';

            return '<div class="alert alert-warning mb-3" role="alert"><h3 class="alert-heading h5">' . $escape(Text::_('COM_DHARMASHOP_QUICK_ORDER_STATUS_MISSING')) . '</h3>'
                . '<p class="mb-2">' . Text::sprintf('COM_DHARMASHOP_QUICK_ORDER_STATUS_MISSING_DESC', $link) . '</p>'
                . '<p class="mb-2">' . $escape(Text::_('COM_DHARMASHOP_QUICK_ORDER_STATUS_URL')) . '</p>'
                . '<p class="mb-2"><input type="text" class="form-control form-control-sm" readonly value="' . $escape(QuickOrder::INSTALL_URL) . '" aria-label="' . $escape(Text::_('COM_DHARMASHOP_QUICK_ORDER_STATUS_URL')) . '"></p>'
                . '<p class="mb-0">' . Text::sprintf('COM_DHARMASHOP_QUICK_ORDER_STATUS_HOW', $install) . '</p></div>';
        }

        $version = QuickOrder::version();
        $code    = QuickOrder::formCode();
        $service = new FormService();
        $forms   = $service->triggers();
        $out     = '<div class="alert alert-success mb-3" role="alert">'
            . Text::sprintf('COM_DHARMASHOP_QUICK_ORDER_STATUS_OK', $link, $escape($version !== '' ? $version : '—')) . '</div>';

        if ($code === '' || !isset($forms[$code])) {
            $out .= '<div class="alert alert-warning mb-3" role="alert">' . Text::sprintf('COM_DHARMASHOP_QUICK_ORDER_STATUS_NO_FORM', '<code>' . $escape($code) . '</code>') . '</div>';
        } elseif (!isset($service->contexts()[$code])) {
            $out .= '<div class="alert alert-info mb-3" role="alert">' . Text::sprintf('COM_DHARMASHOP_QUICK_ORDER_STATUS_NO_CONTEXT', '<code>' . $escape($code) . '</code>') . '</div>';
        }

        return $out;
    }
}
