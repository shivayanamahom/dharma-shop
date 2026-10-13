<?php
namespace Dharma\Component\DharmaShopImport\Administrator\View\Jobs;
use Dharma\Component\DharmaShopImport\Administrator\Import\Access;
use Joomla\CMS\Toolbar\{Toolbar,ToolbarHelper};
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    protected $option='com_dharmashop_import';protected array $items=[];protected $pagination;
    public function display($tpl=null):void{
        Access::require('dharmashop.import.report');$this->items=$this->getModel()->getItems();$this->pagination=$this->getModel()->getPagination();
        ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_IMPORT_HISTORY'),'history');$t=Toolbar::getInstance();
        $t->linkButton('profiles','COM_DHARMASHOP_IMPORT_PROFILES')->url('index.php?option='.$this->option.'&view=profiles')->icon('icon-list');
        if($this->getCurrentUser()->authorise('core.delete',$this->option)){
            $t->delete('jobs.delete')->message('COM_DHARMASHOP_IMPORT_JOBS_DELETE_CONFIRM')->listCheck(true);
            $t->confirmButton('clear','COM_DHARMASHOP_IMPORT_JOBS_CLEAR','jobs.clear')->icon('icon-trash')->message('COM_DHARMASHOP_IMPORT_JOBS_CLEAR_CONFIRM');
        }
        parent::display($tpl);
    }
}
