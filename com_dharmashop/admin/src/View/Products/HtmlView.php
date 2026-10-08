<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Products;

use Dharma\Component\DharmaShop\Administrator\Maintenance\MaintenanceService;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * Список товаров.
 */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var object[] */
    protected array $items = [];

    protected ?Pagination $pagination = null;

    public ?Form $filterForm = null;

    public array $activeFilters = [];

    /** Форма окна «Пакетная обработка» (forms/batch_product.xml). */
    public ?Form $batchForm = null;

    public function display($tpl = null): void
    {
        /** @var \Dharma\Component\DharmaShop\Administrator\Model\ProductsModel $model */
        $model = $this->getModel();

        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->batchForm = Form::getInstance('com_dharmashop.batch.product', JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/batch_product.xml', ['control' => '']);

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // В окне выбора товара (layout=modal) панели инструментов нет.
        if ($this->getLayout() !== 'modal') {
            $this->addToolbar();
        }

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $canDo = ContentHelper::getActions('com_dharmashop');
        $toolbar = Toolbar::getInstance();
        $state = $this->getModel()->getState();

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_PRODUCTS_TITLE'), 'cart');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('product.add');
        }

        if ($canDo->get('core.edit.state') || $canDo->get('core.delete')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->toggleSplit(false)
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if ($canDo->get('core.edit.state')) {
                $childBar->publish('products.publish')->listCheck(true);
                $childBar->unpublish('products.unpublish')->listCheck(true);
                $childBar->archive('products.archive')->listCheck(true);
                $childBar->standardButton('resave', 'COM_DHARMASHOP_RESAVE', 'products.resave')->icon('icon-refresh')->listCheck(true);
                $childBar->checkin('products.checkin');

                if ((string) $state->get('filter.published') !== '-2') {
                    $childBar->trash('products.trash')->listCheck(true);
                }
            }

            // Пакетная обработка: окно с набором операций (состав — в tmpl/products/default_batch_body.php и ProductModel::$batch_commands).
            if ($canDo->get('core.create') && $canDo->get('core.edit')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_DHARMASHOP_BATCH_OPTIONS'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('1200px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }
        }

        if ($canDo->get('core.manage')) {
            $commands = $toolbar->dropdownButton('commands-group')
                ->toggleSplit(false)
                ->text('COM_DHARMASHOP_COMMANDS')
                ->icon('icon-terminal')
                ->buttonClass('btn btn-action');
            $commandsBar = $commands->getChildToolbar();

            foreach (MaintenanceService::COMMANDS as $name => $label) {
                $commandsBar->standardButton('cmd-' . $name, $label, 'products.cmd' . ucfirst($name))->icon('icon-terminal')->listCheck(false);
            }
            foreach (['prepare'=>'PREPARE', 'clear'=>'CLEAR'] as $operation=>$label) {
                $commandsBar->linkButton('images-'.$operation, 'COM_DHARMASHOP_IMAGE_COMMAND_'.$label)
                    ->url(Route::_('index.php?option=com_dharmashop&view=imagecommands&operation='.$operation, false))
                    ->icon('icon-images');
            }
        }

        if ($state->get('filter.published') == -2 && $canDo->get('core.delete')) {
            $toolbar->delete('products.delete', 'JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_dharmashop');
        }
    }
}
