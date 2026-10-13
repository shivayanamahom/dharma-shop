<?php

/**
 * Dharma Shop — группы самостоятельных товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Productgroups;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected array $items = [];
    protected ?Pagination $pagination = null;
    public ?Form $filterForm = null;
    public array $activeFilters = [];
    public ?Form $batchForm = null;

    public function display($tpl = null): void
    {
        // Модальный выбор группы в карточке товара — для всех, кто правит товары; управление группами — отдельное право.
        if (!$this->getCurrentUser()->authorise($this->getLayout() === 'modal' ? 'core.edit' : 'dharmashop.groups.manage', 'com_dharmashop')) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        if ($this->getLayout() !== 'modal') {
            $this->batchForm = Form::getInstance('com_dharmashop.batch.productgroup', JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/batch_productgroup.xml', ['control' => '']);
        }

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if ($this->getLayout() !== 'modal') {
            $this->addToolbar();
        } else {
            $money = new MoneyFormatter(ComponentHelper::getParams('com_dharmashop'));
            foreach ($this->items as $item) {
                $item->picker_image = $item->cover ? ImageUrl::resolve($item->cover, 'admin.picker.group') : '';
                $item->picker_price = $item->price_min === null ? Text::_('COM_DHARMASHOP_PRICE_ON_REQUEST') : $money->range($item->price_min, $item->price_max);
            }
        }
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $model = $this->getModel();
        $canDo = ContentHelper::getActions('com_dharmashop');
        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_PRODUCTGROUPS_TITLE'), 'layer-group');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('productgroup.add');
        }

        if ($canDo->get('core.edit.state') || ($canDo->get('core.create') && $canDo->get('core.edit'))) {
            $dropdown = $toolbar->dropdownButton('group-actions')
                ->toggleSplit(false)
                ->text('COM_DHARMASHOP_SELECTED_ACTIONS')
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(false);

            $childBar = $dropdown->getChildToolbar();
            if ($canDo->get('core.edit.state')) {
                $childBar->publish('productgroups.publish')->listCheck(true);
                $childBar->unpublish('productgroups.unpublish')->listCheck(true);
                $childBar->archive('productgroups.archive')->listCheck(true);
                $childBar->checkin('productgroups.checkin');
                if ((int) $model->getState('filter.published') !== -2) { $childBar->trash('productgroups.trash')->listCheck(true); }
            }

            if ($canDo->get('core.create') && $canDo->get('core.edit')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_DHARMASHOP_BATCH_OPTIONS_PRODUCTGROUPS'))
                    ->url('#joomla-dialog-batch-productgroups')
                    ->modalWidth('1200px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }
        }

        if ((int) $model->getState('filter.published') === -2 && $canDo->get('core.delete')) {
            $toolbar->delete('productgroups.delete', 'JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_dharmashop');
        }

    }
}
