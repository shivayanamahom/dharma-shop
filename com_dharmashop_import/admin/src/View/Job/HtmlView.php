<?php
namespace Dharma\Component\DharmaShopImport\Administrator\View\Job;
use Dharma\Component\DharmaShopImport\Administrator\Import\{Access,Workflow};
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\{Toolbar,ToolbarHelper};
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    protected $option='com_dharmashop_import';protected array $job;protected array $rows;protected array $summary;protected int $after;protected string $filter;
    public function display($tpl=null):void{
        $w=new Workflow(Factory::getContainer()->get(DatabaseInterface::class));$input=Factory::getApplication()->getInput();$this->job=$w->job($input->getInt('id'));Access::job($this->job);
        $this->after=max(0,$input->getInt('after'));$f=$input->getCmd('filter');$this->filter=in_array($f,['error','create','update','unchanged'],true)?$f:'';
        $this->rows=$w->rows((int)$this->job['id'],$this->after,100,$this->filter);$this->summary=$w->summary((int)$this->job['id']);
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_IMPORT_RESULT').' #'.$this->job['id'],'upload');$t=Toolbar::getInstance();
        $t->linkButton('profiles','COM_DHARMASHOP_IMPORT_PROFILES')->url('index.php?option='.$this->option.'&view=profiles')->icon('icon-list');
        $t->linkButton('history','COM_DHARMASHOP_IMPORT_HISTORY')->url('index.php?option='.$this->option.'&view=jobs')->icon('icon-history');
        if($this->summary['errors']){$t->linkButton('errors','COM_DHARMASHOP_IMPORT_ERRORS_CSV')->url('index.php?option='.$this->option.'&task=job.errors&id='.(int)$this->job['id'].'&'.Session::getFormToken().'=1')->icon('icon-download');}
        parent::display($tpl);
    }
}
