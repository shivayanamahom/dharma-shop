<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Shippings;

use Joomla\CMS\Form\Form;
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
        $user = $this->getCurrentUser();

        if (!$user->authorise('dharmashop.shipping.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_SHIPPINGS_TITLE'), 'truck');

        if ($user->authorise('core.create', $this->option)) {
            $toolbar->addNew('shipping.add');
        }

        if ($user->authorise('core.edit.state', $this->option)) {
            $toolbar->publish('shippings.publish')->listCheck(true);
            $toolbar->unpublish('shippings.unpublish')->listCheck(true);
            $toolbar->checkin('shippings.checkin');
        }

        if ($user->authorise('core.delete', $this->option)) {
            $toolbar->delete('shippings.delete', 'JTOOLBAR_DELETE')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
        }

        parent::display($tpl);
    }
}
