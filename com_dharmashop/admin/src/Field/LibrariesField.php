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

use Dharma\Component\DharmaShop\Administrator\Library\LibraryUpdater;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Таблица сторонних библиотек (Swiper, Fancybox) со сравнением версий и кнопками «Обновить» / «Откатить» / «Проверить».
 * Кнопки — обычные кнопки формы с formaction на параметр libraries_action (его принимает DisplayController и передаёт LibrariesController;
 * параметр task не годится: в теле формы настроек есть пустое поле task, оно перекрывает адрес). Токен — формы настроек; значения в настройки не сохраняются.
 */
class LibrariesField extends FormField
{
    protected $type = 'Libraries';

    protected function getLabel(): string
    {
        return '';
    }

    protected function getInput(): string
    {
        $updater = new LibraryUpdater();
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = static fn (string $task, string $key = ''): string => 'index.php?option=com_dharmashop&libraries_action=' . $task . ($key !== '' ? '&library=' . $key : '');
        $rows = '';

        foreach (array_keys($updater->libraries()) as $key) {
            // Страница настроек не должна обращаться к GitHub. Актуальность обновляется кнопкой ниже.
            $status = $updater->status($key, false);
            $installed = $status['installed'] ?? Text::_('COM_DHARMASHOP_LIB_NOT_INSTALLED');
            $state = '';
            $buttons = '';

            if ($status['latest'] === null) {
                $latest = '—';
                $state = '<span class="badge text-bg-secondary">' . $escape(Text::_('COM_DHARMASHOP_LIB_NOT_CHECKED')) . '</span>';
            } elseif ($status['error'] !== null) {
                $latest = '—';
                $state = '<span class="text-danger">' . $escape(str_starts_with((string) $status['error'], 'COM_DHARMASHOP_') ? Text::_($status['error']) : Text::_('COM_DHARMASHOP_LIB_ERROR_GENERIC')) . '</span>';
            } else {
                $latest = (string) $status['latest'];
                $state = $status['newer']
                    ? '<span class="badge text-bg-warning">' . $escape(Text::_($status['major'] ? 'COM_DHARMASHOP_LIB_MAJOR_AVAILABLE' : 'COM_DHARMASHOP_LIB_UPDATE_AVAILABLE')) . '</span>'
                    : '<span class="badge text-bg-success">' . $escape(Text::_('COM_DHARMASHOP_LIB_UP_TO_DATE')) . '</span>';
            }

            if ($status['installed'] !== null && $status['newer']) {
                $buttons .= '<button type="submit" class="btn btn-sm btn-primary me-1" formaction="' . $escape($action('update', $key)) . '" formnovalidate>'
                    . $escape(Text::sprintf('COM_DHARMASHOP_LIB_UPDATE_TO', $latest)) . '</button>';

                if ($status['major']) {
                    $buttons .= '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="confirm_major" value="1" id="dharmashop-major-' . $escape($key) . '">'
                        . '<label class="form-check-label small" for="dharmashop-major-' . $escape($key) . '">' . $escape(Text::_('COM_DHARMASHOP_LIB_CONFIRM_MAJOR')) . '</label></div>';
                }
            }

            if ($status['backup'] !== null) {
                $buttons .= '<button type="submit" class="btn btn-sm btn-outline-secondary" formaction="' . $escape($action('rollback', $key)) . '" formnovalidate>'
                    . $escape(Text::sprintf('COM_DHARMASHOP_LIB_ROLLBACK_TO', $status['backup'])) . '</button>';
            }

            $note = $status['pluginEnabled'] ? '' : '<div class="small text-danger">' . $escape(Text::_('COM_DHARMASHOP_LIB_PLUGIN_DISABLED')) . '</div>';
            $rows .= '<tr><th scope="row">' . $escape($status['title']) . '<div class="small text-body-secondary fw-normal">' . $escape($status['license']) . '</div>' . $note . '</th>'
                . '<td>' . $escape($installed) . '</td><td>' . $escape($latest) . '</td><td>' . $state . '</td><td>' . $buttons . '</td></tr>';
        }

        return '<div class="table-responsive"><table class="table align-middle">'
            . '<caption class="visually-hidden">' . $escape(Text::_('COM_DHARMASHOP_LIB_CAPTION')) . '</caption>'
            . '<thead><tr><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_LIB_COL_LIBRARY')) . '</th><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_LIB_COL_INSTALLED'))
            . '</th><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_LIB_COL_LATEST')) . '</th><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_LIB_COL_STATUS')) . '</th><th scope="col"></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '<button type="submit" class="btn btn-sm btn-outline-primary" formaction="' . $escape($action('check')) . '" formnovalidate>' . $escape(Text::_('COM_DHARMASHOP_LIB_CHECK_NOW')) . '</button>';
    }
}
