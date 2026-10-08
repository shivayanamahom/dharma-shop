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

use Dharma\Component\DharmaShop\Administrator\Library\LibraryUpdater;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Кнопки вкладки «Расширения» в настройках: обновить / откатить библиотеку (Swiper, Fancybox), проверить версии.
 * Задачи: libraries.update, libraries.rollback, libraries.check. Токен и право core.admin обязательны.
 */
class LibrariesController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function update(): void
    {
        $this->run('update');
    }

    public function rollback(): void
    {
        $this->run('rollback');
    }

    public function check(): void
    {
        $this->run('check');
    }

    private function run(string $action): void
    {
        $this->checkToken();
        $url = Route::_('index.php?option=com_config&view=component&component=com_dharmashop', false);

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_dharmashop')) {
            $this->setRedirect($url, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $updater = new LibraryUpdater();

        if ($action === 'check') {
            $updater->forgetCache();
            $this->setRedirect($url, Text::_('COM_DHARMASHOP_LIB_CHECKED'));

            return;
        }

        $key = $this->input->getCmd('library');

        if (!$updater->exists($key)) {
            $this->setRedirect($url, Text::_('COM_DHARMASHOP_LIB_ERROR_UNKNOWN'), 'error');

            return;
        }

        $title = (string) $updater->libraries()[$key]['title'];

        try {
            if ($action === 'update') {
                $version = $updater->update($key, $this->input->getInt('confirm_major') === 1);
                $this->setRedirect($url, Text::sprintf('COM_DHARMASHOP_LIB_UPDATED', $title, $version));
            } else {
                $version = $updater->rollback($key);
                $this->setRedirect($url, Text::sprintf('COM_DHARMASHOP_LIB_ROLLED_BACK', $title, $version));
            }
        } catch (\Throwable $e) {
            $message = str_starts_with($e->getMessage(), 'COM_DHARMASHOP_') ? Text::_($e->getMessage()) : Text::_('COM_DHARMASHOP_LIB_ERROR_GENERIC');
            $this->setRedirect($url, $title . ': ' . $message, 'error');
        }
    }
}
