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

use Dharma\Component\DharmaShop\Administrator\Maintenance\MaintenanceService;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/**
 * Операции над списком категорий выбранного типа: публикация, архив, корзина, удаление, порядок, «Перестроить».
 */
class CategoriesController extends AdminController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    protected $view_list = 'categories';

    public function getModel($name = 'Category', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    protected function getRedirectToListAppend(): string
    {
        return '&extension=' . CategoryTypes::normalize($this->input->getString('extension', CategoryTypes::CATEGORY));
    }

    /** Кнопка «Перестроить»: пересчитывает дерево и пути категорий. */
    public function rebuild(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_dharmashop')) {
            $this->setRedirect($this->getRedirectUrlToList(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $model = $this->getModel();

        if ($model->rebuild()) {
            $this->setRedirect($this->getRedirectUrlToList(), Text::_('COM_DHARMASHOP_REBUILD_SUCCESS'));
        } else {
            $this->setRedirect($this->getRedirectUrlToList(), Text::sprintf('COM_DHARMASHOP_REBUILD_FAILED', $model->getError()), 'error');
        }
    }

    /** Команда «Пересобрать витринный индекс товаров». */
    public function cmdIndex(): void
    {
        $this->runCommand('index');
    }

    /** Команда «Пересчитать цену и наличие товаров». */
    public function cmdProducts(): void
    {
        $this->runCommand('products');
    }

    /** Команда «Пересчитать акционные цены по датам». */
    public function cmdSales(): void
    {
        $this->runCommand('sales');
    }

    /**
     * Запускает команду обслуживания на время не дольше бюджета. Если каталог не закончился, курсор хранится в сессии
     * пользователя, и повторный запуск продолжает с места остановки (полный прогон больших каталогов — консольной командой).
     */
    private function runCommand(string $name): void
    {
        $this->checkToken();

        if (!MaintenanceService::isCommand($name) || !$this->app->getIdentity()->authorise('core.manage', 'com_dharmashop')) {
            $this->setRedirect($this->getRedirectUrlToList(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $key = 'com_dharmashop.command.' . $name;
        $cursor = (int) $this->app->getUserState($key, 0);

        try {
            $result = $this->app->bootComponent('com_dharmashop')->getServices()->maintenance()->run($name, $cursor);
        } catch (\Throwable $e) {
            $this->app->setUserState($key, null);
            $this->setRedirect($this->getRedirectUrlToList(), Text::sprintf('COM_DHARMASHOP_CMD_FAILED', $e->getMessage()), 'error');

            return;
        }

        if ($result['done']) {
            $this->app->setUserState($key, null);
            $this->setRedirect($this->getRedirectUrlToList(), Text::sprintf('COM_DHARMASHOP_CMD_DONE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']));

            return;
        }

        $this->app->setUserState($key, $result['cursor']);
        $this->setRedirect(
            $this->getRedirectUrlToList(),
            Text::sprintf('COM_DHARMASHOP_CMD_CONTINUE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']),
            'warning'
        );
    }
}
