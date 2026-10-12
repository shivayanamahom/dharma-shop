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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse;

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

    /** Separate bulk price operation for the default price list; product metadata is not touched. */
    public function batchPrices(): void
    {
        $this->checkToken();
        $ajax = $this->input->getBool('batch_ajax');
        $redirect = $this->listUrl();
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.edit', 'com_dharmashop')) {
            if ($ajax) {
                BatchProgressResponse::send(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')], 403);
            }
            $this->setRedirect($redirect, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            return;
        }

        try {
            $form = Form::getInstance('com_dharmashop.batch.prices', JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/batch_prices.xml', ['control' => '']);
            $filtered = $form->filter($this->input->post->getArray());
            if ($filtered === false || !$form->validate($filtered)) {
                throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID');
            }
            $data = (array) ($filtered['batch_prices'] ?? []);
            $service = $this->app->bootComponent('com_dharmashop')->getServices();
            $updater = new \Dharma\Component\DharmaShop\Administrator\Pricing\BatchPriceUpdater(
                Factory::getContainer()->get(DatabaseInterface::class),
                $service->rebuilder(),
                $service->indexer(),
            );
            $result = $updater->apply((array) $this->input->post->get('cid', [], 'int'), $data, $user);
            if ($ajax) {
                BatchProgressResponse::send(['success' => true, 'processed' => (int) $result['updated'] + (int) $result['skipped'], 'updated' => (int) $result['updated'], 'skipped' => (int) $result['skipped']]);
            }
            $this->app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_PRICE_DONE', $result['updated'], $result['skipped']));
            foreach ($result['warnings'] as $warning) {
                $this->app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_PRICE_SKIPPED', $warning['title'], Text::_($warning['key'])), 'warning');
            }
        } catch (\Throwable $error) {
            if ($ajax) {
                $message = $error instanceof \InvalidArgumentException ? Text::_($error->getMessage()) : Text::_('COM_DHARMASHOP_BATCH_PRICE_FAILED');
                BatchProgressResponse::send(['success' => false, 'error' => $message], 400);
            }
                $message = $error instanceof \InvalidArgumentException ? Text::_($error->getMessage()) : Text::_('COM_DHARMASHOP_PROGRESS_FAILED');
            $this->app->enqueueMessage($message, 'error');
        }

        $this->setRedirect($redirect);
    }

    /** Восстанавливает только производные индексы категорий и поиска. */
    public function cmdIndex(): void
    {
        $this->runCommand('index');
    }

    /** Пересчитывает правила, цену, наличие и связанные индексы для всего каталога. */
    public function cmdProducts(): void
    {
        $this->runCommand('products');
    }

    /** Обновляет только итоговые цены, у которых наступила или завершилась скидка. */
    public function cmdSales(): void
    {
        $this->runCommand('sales');
    }

    /** Пошагово конвертирует уже загруженные изображения магазина в WebP. */
    public function cmdWebp(): void
    {
        $this->runCommand('webp');
    }

    public function cmdFacets(): void
    {
        $this->runCommand('facets');
    }

    /**
     * Запускает команду обслуживания на время не дольше бюджета. Если каталог не закончился, курсор хранится в сессии
     * пользователя, и повторный запуск продолжает с места остановки (полный прогон больших каталогов — консольной командой).
     */
    private function runCommand(string $name): void
    {
        $this->checkToken();

        if (!MaintenanceService::isCommand($name) || !$this->app->getIdentity()->authorise('core.manage', 'com_dharmashop')) {
            if ($this->input->getBool('batch_ajax')) {
                BatchProgressResponse::send(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')], 403);
            }
            $this->setRedirect($this->listUrl(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $ajax = $this->input->getBool('batch_ajax');
        $key = 'com_dharmashop.command.' . $name;
        $stateKey = 'com_dharmashop.command.progress.' . $name;
        $state = (array) $this->app->getUserState($stateKey, []);
        $cursor = $state['cursor'] ?? ($name === 'webp' ? (array) $this->app->getUserState($key, []) : (int) $this->app->getUserState($key, 0));
        $total = (int) ($state['total'] ?? 0);
        $processedTotal = (int) ($state['processed'] ?? 0);

        try {
            $maintenance = $this->app->bootComponent('com_dharmashop')->getServices()->maintenance();
            if ($total <= 0) {
                $total = $maintenance->countTotal($name);
            }
            $result = $maintenance->run($name, $cursor);
        } catch (\Throwable $e) {
            $this->app->setUserState($key, null);
            $this->app->setUserState($stateKey, null);
            if ($ajax) {
                BatchProgressResponse::send(['success' => false, 'error' => Text::_('COM_DHARMASHOP_CMD_AJAX_FAILED')], 500);
            }
            $this->setRedirect($this->listUrl(), Text::sprintf('COM_DHARMASHOP_CMD_FAILED', $e->getMessage()), 'error');

            return;
        }

        $processedTotal += (int) $result['processed'];
        if ($ajax) {
            if ($result['done']) {
                $this->app->setUserState($key, null);
                $this->app->setUserState($stateKey, null);
            } else {
                $this->app->setUserState($key, $result['cursor']);
                $this->app->setUserState($stateKey, ['cursor' => $result['cursor'], 'processed' => $processedTotal, 'total' => $total]);
            }
            BatchProgressResponse::send([
                'success' => true,
                'processed' => min($processedTotal, $total),
                'total' => $total,
                'done' => (bool) $result['done'],
                'title' => Text::_(MaintenanceService::COMMANDS[$name]),
                'unit' => $name === 'facets' ? Text::_('COM_DHARMASHOP_PROGRESS_FILTER_SCOPES') : ($name === 'webp' ? Text::_('COM_DHARMASHOP_PROGRESS_RECORDS') : Text::_('COM_DHARMASHOP_PROGRESS_PRODUCTS')),
                'converted' => (int) ($result['converted'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0),
            ], 200);
        }

        if ($result['done']) {
            $this->app->setUserState($key, null);
            $this->app->setUserState($stateKey, null);

            if ($name === 'webp') {
                $message = Text::sprintf('COM_DHARMASHOP_CMD_WEBP_DONE', (int) ($result['converted'] ?? 0), (int) ($result['failed'] ?? 0));
            } else {
                $message = Text::sprintf('COM_DHARMASHOP_CMD_DONE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']);
            }

            $this->setRedirect($this->listUrl(), $message);

            return;
        }

        $this->app->setUserState($key, $result['cursor']);
        $this->app->setUserState($stateKey, ['cursor' => $result['cursor'], 'processed' => $processedTotal, 'total' => $total]);
        $message = $name === 'webp'
            ? Text::sprintf('COM_DHARMASHOP_CMD_WEBP_CONTINUE', (int) ($result['converted'] ?? 0), (int) ($result['failed'] ?? 0))
            : Text::sprintf('COM_DHARMASHOP_CMD_CONTINUE', Text::_(MaintenanceService::COMMANDS[$name]), $result['processed']);
        $this->setRedirect($this->listUrl(), $message, 'warning');
    }
}
