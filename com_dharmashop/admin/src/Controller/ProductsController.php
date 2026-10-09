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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

/**
 * Операции над списком товаров: публикация, архив, корзина, удаление, «Пересохранить» и команды обслуживания.
 */
class ProductsController extends AdminController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Product', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /** Open native group form with selected existing products; no mutation before Save. */
    public function combine(): void
    {
        $this->checkToken();
        $user=$this->app->getIdentity();
        $ids=array_values(array_unique(array_filter(ArrayHelper::toInteger((array)$this->input->get('cid',[],'array')))));
        if (!$user->authorise('core.create','com_dharmashop') || !$ids) {
            $this->setRedirect($this->listUrl(),Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),'error');return;
        }
        foreach($ids as $id) {if(!$user->authorise('core.edit','com_dharmashop.product.'.$id)) {$this->setRedirect($this->listUrl(),Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),'error');return;}}
        $this->app->setUserState('com_dharmashop.edit.productgroup.data',null);
        $this->app->setUserState('com_dharmashop.group.selection',$ids);
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&task=productgroup.add&from_products=1',false));
    }

    private function listUrl(): string
    {
        return Route::_('index.php?option=com_dharmashop&view=products', false);
    }

    /** Действие «Пересохранить»: пересчёт цены, наличия, правил оформления и индекса у выбранных товаров. */
    public function resave(): void
    {
        $this->checkToken();

        $ids = ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array'));

        if (!$ids) {
            $this->app->enqueueMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
        } else {
            $done = $this->getModel()->resave($ids);
            $this->setMessage(Text::sprintf('COM_DHARMASHOP_N_ITEMS_RESAVED', $done));
        }

        $this->setRedirect($this->listUrl());
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

    /** Пошагово конвертирует уже загруженные изображения магазина в WebP. */
    public function cmdWebp(): void
    {
        $this->runCommand('webp');
    }

    /**
     * Запускает команду обслуживания на время не дольше бюджета. Если каталог не закончился, курсор хранится в сессии
     * пользователя, и повторный запуск продолжает с места остановки (полный прогон больших каталогов — консольной командой).
     */
    private function runCommand(string $name): void
    {
        $this->checkToken();

        if (!MaintenanceService::isCommand($name) || !$this->app->getIdentity()->authorise('core.manage', 'com_dharmashop')) {
            $this->setRedirect($this->listUrl(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $key = 'com_dharmashop.command.' . $name;
        $cursor = $name === 'webp'
            ? (array) $this->app->getUserState($key, [])
            : (int) $this->app->getUserState($key, 0);

        try {
            $result = $this->app->bootComponent('com_dharmashop')->getServices()->maintenance()->run($name, $cursor);
        } catch (\Throwable $e) {
            $this->app->setUserState($key, null);
            $this->setRedirect($this->listUrl(), Text::sprintf('COM_DHARMASHOP_CMD_FAILED', $e->getMessage()), 'error');

            return;
        }

        if ($result['done']) {
            $this->app->setUserState($key, null);

            if ($name === 'webp') {
                $message = Text::sprintf('COM_DHARMASHOP_CMD_WEBP_DONE', (int) ($result['converted'] ?? 0), (int) ($result['failed'] ?? 0));
            } else {
                $message = Text::sprintf('COM_DHARMASHOP_CMD_DONE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']);
            }

            $this->setRedirect($this->listUrl(), $message);

            return;
        }

        $this->app->setUserState($key, $result['cursor']);
        $message = $name === 'webp'
            ? Text::sprintf('COM_DHARMASHOP_CMD_WEBP_CONTINUE', (int) ($result['converted'] ?? 0), (int) ($result['failed'] ?? 0))
            : Text::sprintf('COM_DHARMASHOP_CMD_CONTINUE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']);
        $this->setRedirect($this->listUrl(), $message, 'warning');
    }
}
