<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Controller;
use Dharma\Component\DharmaShopImport\Administrator\Import\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** История запусков: удаление выбранных и очистка завершённых. Нужны право на отчёты и право удаления. */
class JobsController extends \Joomla\CMS\MVC\Controller\BaseController
{
    use RequiresActionTrait;
    protected $option='com_dharmashop_import';
    protected function requiredActions():array{return ['*'=>'dharmashop.import.report'];}
    private function workflow():Workflow{return new Workflow(Factory::getContainer()->get(DatabaseInterface::class));}
    private function allowDelete():void{if(!$this->app->getIdentity()->authorise('core.delete',$this->option)){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}}
    private function finish(int $deleted,int $kept=0):void{
        $this->setMessage(Text::sprintf('COM_DHARMASHOP_IMPORT_JOBS_DELETED',$deleted).($kept?' '.Text::sprintf('COM_DHARMASHOP_IMPORT_JOBS_KEPT',$kept):''),$kept&&!$deleted?'warning':'message');
        $this->setRedirect('index.php?option='.$this->option.'&view=jobs');
    }
    public function delete():void{
        $this->checkToken();$this->allowDelete();$w=$this->workflow();$ids=array_filter(array_map('intval',(array)$this->input->post->get('cid',[],'array')));$deleted=0;
        foreach($ids as $id){try{if($w->remove($id)){$deleted++;}}catch(\Throwable){}}
        $this->finish($deleted,count($ids)-$deleted);
    }
    public function clear():void{$this->checkToken();$this->allowDelete();$this->finish($this->workflow()->clear());}
}
