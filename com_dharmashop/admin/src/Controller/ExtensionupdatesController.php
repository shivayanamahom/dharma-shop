<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Кнопки вкладки «Расширения» в настройках: проверить обновления, обновить расширение, обновить всё, сменить канал.
 * Обновление выполняет штатная модель Joomla; нужны токен, право core.admin на магазин и core.manage на «Установку расширений».
 */
class ExtensionupdatesController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function check(): void
    {
        $this->run('check');
    }

    public function update(): void
    {
        $this->run('update');
    }

    public function updateall(): void
    {
        $this->run('updateall');
    }

    public function channel(): void
    {
        $this->run('channel');
    }

    public function rollback(): void
    {
        $this->run('rollback');
    }

    private function run(string $action): void
    {
        $this->checkToken();
        // Кнопки есть и на странице «Обновления» (return=updates), и во вкладке «Расширения» настроек.
        $url = Route::_($this->input->getCmd('return') === 'updates' ? 'index.php?option=com_dharmashop&view=updates' : 'index.php?option=com_config&view=component&component=com_dharmashop', false);
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin', 'com_dharmashop') || !$user->authorise('core.manage', 'com_installer')) {
            $this->setRedirect($url, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        // Сообщения штатной модели обновления («Обновление выполнено…») — из языка com_installer.
        $this->app->getLanguage()->load('com_installer', JPATH_ADMINISTRATOR);
        $updates = new ExtensionUpdates();

        try {
            switch ($action) {
                case 'check':
                    $count = $updates->check();
                    $this->setRedirect($url, Text::sprintf($count ? 'COM_DHARMASHOP_EXT_CHECKED' : 'COM_DHARMASHOP_EXT_NONE', $count));
                    break;
                case 'rollback':
                    $version = $updates->rollback($this->input->getInt('extension_id'));
                    $this->setRedirect($url, Text::sprintf('COM_DHARMASHOP_EXT_ROLLED_BACK', $version));
                    break;
                case 'channel':
                    $updates->setStability($this->input->post->getInt('ext_channel', 4));
                    $this->setRedirect($url, Text::_('COM_DHARMASHOP_EXT_CHANNEL_SAVED'));
                    break;
                default:
                    $ids = $action === 'updateall' ? $updates->allUpdateIds() : [$this->input->getInt('update_id')];
                    $ids = array_values(array_filter(array_map('intval', $ids)));
                    if (!$ids) {
                        $this->setRedirect($url, Text::sprintf('COM_DHARMASHOP_EXT_NONE', 0), 'warning');
                        break;
                    }
                    $ok = $updates->update($ids);
                    $this->setRedirect($url, Text::sprintf($ok ? 'COM_DHARMASHOP_EXT_UPDATED' : 'COM_DHARMASHOP_EXT_UPDATE_FAILED', count($ids)), $ok ? 'message' : 'error');
            }
        } catch (\Throwable $e) {
            $this->setRedirect($url, Text::_(str_starts_with($e->getMessage(), 'COM_DHARMASHOP_') ? $e->getMessage() : 'COM_DHARMASHOP_EXT_UPDATE_FAILED'), 'error');
        }
    }
}
