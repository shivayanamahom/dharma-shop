<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Fieldgroups;

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

        $canDo = ContentHelper::getActions('com_dharmashop');
        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_FIELDGROUPS_TITLE'), 'layer-group');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('fieldgroup.add');
        }

        if ($canDo->get('core.edit.state')) {
            $toolbar->publish('fieldgroups.publish')->listCheck(true);
            $toolbar->unpublish('fieldgroups.unpublish')->listCheck(true);
            $toolbar->archive('fieldgroups.archive')->listCheck(true);
            $toolbar->checkin('fieldgroups.checkin');

            if ((int) $model->getState('filter.published') !== -2) {
                $toolbar->trash('fieldgroups.trash')->listCheck(true);
            }
        }

        if ((int) $model->getState('filter.published') === -2 && $canDo->get('core.delete')) {
            $toolbar->delete('fieldgroups.delete', 'JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_dharmashop');
        }

        parent::display($tpl);
    }
}
