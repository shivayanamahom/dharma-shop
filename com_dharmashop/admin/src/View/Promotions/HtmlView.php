<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\View\Promotions;
use Joomla\CMS\Form\Form;use Joomla\CMS\Language\Text;use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;use Joomla\CMS\Pagination\Pagination;use Joomla\CMS\Toolbar\ToolbarHelper;
\defined('_JEXEC') or die;
class HtmlView extends BaseHtmlView
{
    protected $option='com_dharmashop';protected array $items=[];protected ?Pagination $pagination=null;public ?Form $filterForm=null;public array $activeFilters=[];
    public function display($tpl=null): void
    {
        $user=$this->getCurrentUser();if(!$user->authorise('dharmashop.promotions.manage',$this->option))throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $model=$this->getModel();$this->items=$model->getItems();$this->pagination=$model->getPagination();$this->filterForm=$model->getFilterForm();$this->activeFilters=$model->getActiveFilters();
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_PROMOTIONS_TITLE'),'tag');if($user->authorise('core.create',$this->option))ToolbarHelper::addNew('promotion.add');if($user->authorise('core.edit.state',$this->option)){ToolbarHelper::publish('promotions.publish');ToolbarHelper::unpublish('promotions.unpublish');}if($user->authorise('core.delete',$this->option))ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE','promotions.delete');parent::display($tpl);
    }
}
