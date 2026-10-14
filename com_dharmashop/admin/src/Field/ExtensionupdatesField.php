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

use Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Ссылка на страницу «Обновления» (пакет и расширения Dharma Shop): сама таблица вынесена оттуда, чтобы не утяжелять страницу настроек.
 * Значок показывает число найденных обновлений (по уже сохранённому результату проверки, в интернет страница не ходит).
 */
class ExtensionupdatesField extends FormField
{
    protected $type = 'Extensionupdates';

    protected function getLabel(): string
    {
        return '';
    }

    protected function getInput(): string
    {
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $count = ExtensionUpdates::pendingCount();

        return '<div class="alert alert-info d-flex flex-wrap align-items-center gap-3"><span>' . $e(Text::_('COM_DHARMASHOP_UPDATES_TAB_TEXT')) . '</span>'
            . '<a class="btn btn-primary" href="' . $e('index.php?option=com_dharmashop&view=updates') . '">' . $e(Text::_('COM_DHARMASHOP_UPDATES_OPEN'))
            . ($count ? ' <span class="badge text-bg-warning">' . $count . '</span>' : '') . '</a></div>';
    }
}
