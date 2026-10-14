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
 * Таблица расширений Dharma Shop (пакет, компоненты, плагины, модули): установленная и доступная версия, «Обновить» у каждого,
 * «Проверить обновления», «Обновить всё», канал (минимальная стабильность Joomla). Кнопки — формы настроек с formaction на параметр
 * ext_action (его принимает DisplayController); обновляет штатная модель com_installer. Страница настроек сама в интернет не ходит.
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
        $updates = new ExtensionUpdates();
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = static fn (string $task, array $extra = []): string => 'index.php?option=com_dharmashop&ext_action=' . $task . ($extra ? '&' . http_build_query($extra) : '');
        $rows = $updates->rows();

        if (!$rows) {
            return '<div class="alert alert-info">' . $e(Text::_('COM_DHARMASHOP_EXT_NO_SITES')) . '</div>';
        }

        $groupName = static fn (array $r): string => match ($r['type']) {
            'package' => Text::_('COM_DHARMASHOP_EXT_GROUP_PACKAGE'),
            'file' => Text::_('COM_DHARMASHOP_EXT_GROUP_FILE'),
            'component' => Text::_('COM_DHARMASHOP_EXT_GROUP_COMPONENT'),
            'module' => Text::_('COM_DHARMASHOP_EXT_GROUP_MODULE'),
            default => Text::sprintf('COM_DHARMASHOP_EXT_GROUP_PLUGIN', $r['folder']),
        };
        $pending = 0;
        $body = '';

        foreach ($rows as $r) {
            $has = $r['available'] !== '' && $r['updateId'] > 0;
            $pending += $has ? 1 : 0;
            $state = !$r['siteEnabled']
                ? '<span class="badge text-bg-secondary">' . $e(Text::_('COM_DHARMASHOP_EXT_SOURCE_OFF')) . '</span>'
                : ($has ? '<span class="badge text-bg-warning">' . $e(Text::_('COM_DHARMASHOP_EXT_UPDATE_AVAILABLE')) . '</span>'
                    : '<span class="badge text-bg-success">' . $e(Text::_('COM_DHARMASHOP_EXT_UP_TO_DATE')) . '</span>');
            $button = $has
                ? '<button type="submit" class="btn btn-sm btn-primary" formaction="' . $e($action('update', ['update_id' => $r['updateId']])) . '" formnovalidate>' . $e(Text::sprintf('COM_DHARMASHOP_EXT_UPDATE_TO', $r['available'])) . '</button>'
                : '';
            $off = $r['enabled'] ? '' : ' <span class="small text-body-secondary">(' . $e(Text::_('COM_DHARMASHOP_EXT_DISABLED')) . ')</span>';
            $body .= '<tr><th scope="row">' . $e($r['title']) . $off . '<div class="small text-body-secondary fw-normal">' . $e($r['element']) . '</div></th><td class="small text-body-secondary">' . $e($groupName($r)) . '</td><td>' . $e($r['installed'])
                . '</td><td>' . $e($r['available'] !== '' ? $r['available'] : '—') . '</td><td>' . $state . '</td><td>' . $button . '</td></tr>';
        }

        $channels = '';
        foreach (ExtensionUpdates::CHANNELS as $value => $key) {
            $channels .= '<option value="' . $value . '"' . ($value === $updates->stability() ? ' selected' : '') . '>' . $e(Text::_('COM_DHARMASHOP_EXT_CHANNEL_' . strtoupper($key))) . '</option>';
        }

        $buttons = '<button type="submit" class="btn btn-sm btn-outline-primary me-2" formaction="' . $e($action('check')) . '" formnovalidate>' . $e(Text::_('COM_DHARMASHOP_EXT_CHECK_NOW')) . '</button>'
            . ($pending ? '<button type="submit" class="btn btn-sm btn-primary me-2" formaction="' . $e($action('updateall')) . '" formnovalidate>' . $e(Text::sprintf('COM_DHARMASHOP_EXT_UPDATE_ALL', $pending)) . '</button>' : '');
        $channel = '<div class="d-flex flex-wrap align-items-center gap-2 mt-3"><label class="form-label mb-0" for="dharmashop-ext-channel">' . $e(Text::_('COM_DHARMASHOP_EXT_CHANNEL')) . '</label>'
            . '<select class="form-select form-select-sm w-auto" name="ext_channel" id="dharmashop-ext-channel">' . $channels . '</select>'
            . '<button type="submit" class="btn btn-sm btn-outline-secondary" formaction="' . $e($action('channel')) . '" formnovalidate>' . $e(Text::_('COM_DHARMASHOP_EXT_CHANNEL_SAVE')) . '</button></div>'
            . '<p class="small text-body-secondary mt-2 mb-0">' . $e(Text::_('COM_DHARMASHOP_EXT_HINT')) . '</p>';

        return '<div class="table-responsive"><table class="table align-middle table-sm">'
            . '<caption class="visually-hidden">' . $e(Text::_('COM_DHARMASHOP_EXT_CAPTION')) . '</caption>'
            . '<thead><tr><th scope="col">' . $e(Text::_('COM_DHARMASHOP_EXT_COL_NAME')) . '</th><th scope="col">' . $e(Text::_('COM_DHARMASHOP_EXT_COL_GROUP')) . '</th><th scope="col">'
            . $e(Text::_('COM_DHARMASHOP_EXT_COL_INSTALLED')) . '</th><th scope="col">' . $e(Text::_('COM_DHARMASHOP_EXT_COL_AVAILABLE')) . '</th><th scope="col">' . $e(Text::_('COM_DHARMASHOP_EXT_COL_STATUS'))
            . '</th><th scope="col"></th></tr></thead><tbody>' . $body . '</tbody></table></div>' . $buttons . $channel;
    }
}
