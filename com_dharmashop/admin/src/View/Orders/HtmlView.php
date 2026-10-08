<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\View\Orders;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
\defined('_JEXEC') or die;
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected array $items = [];
    protected $pagination;
    public $filterForm;
    public array $activeFilters = [];
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('dharmashop.orders.manage',$this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $model = $this->getModel();
        $this->items = $model->getItems(); $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm(); $this->activeFilters = $model->getActiveFilters();
        if ($errors = $model->getErrors()) throw new \RuntimeException(implode("\n",$errors),500);
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_MENU_ORDERS'),'list');
        parent::display($tpl);
    }
}
