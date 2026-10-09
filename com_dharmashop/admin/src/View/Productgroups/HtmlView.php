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

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

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

        if ($canDo->get('core.edit.state')) {
            $toolbar->publish('productgroups.publish')->listCheck(true);
            $toolbar->unpublish('productgroups.unpublish')->listCheck(true);
            $toolbar->archive('productgroups.archive')->listCheck(true);
            $toolbar->checkin('productgroups.checkin');

            if ((int) $model->getState('filter.published') !== -2) {
                $toolbar->trash('productgroups.trash')->listCheck(true);
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
