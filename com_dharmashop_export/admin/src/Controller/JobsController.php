<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Controller;
use Dharma\Component\DharmaShopExport\Administrator\Export\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** История запусков: удаление выбранных и очистка завершённых. Нужны право на историю и право удаления. */
class JobsController extends \Joomla\CMS\MVC\Controller\BaseController {
 use RequiresActionTrait;
 protected $option='com_dharmashop_export';
 protected function requiredActions():array {return ['*'=>'dharmashop.export.report'];}
 private function workflow():Workflow {return new Workflow(Factory::getContainer()->get(DatabaseInterface::class));}
 private function finish(int $deleted,int $kept=0):void {
  $this->setMessage(Text::sprintf('COM_DHARMASHOP_EXPORT_JOBS_DELETED',$deleted).($kept?' '.Text::sprintf('COM_DHARMASHOP_EXPORT_JOBS_KEPT',$kept):''),$kept&&!$deleted?'warning':'message');
  $this->setRedirect('index.php?option='.$this->option.'&view=jobs');
 }
 private function allowDelete():void {if(!$this->app->getIdentity()->authorise('core.delete',$this->option)){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}}
 public function delete():void {
  $this->checkToken();$this->allowDelete();$workflow=$this->workflow();$ids=array_filter(array_map('intval',(array)$this->input->post->get('cid',[],'array')));$deleted=0;
  foreach($ids as $id){try{if($workflow->remove($id)){$deleted++;}}catch(\Throwable){}}
  $this->finish($deleted,count($ids)-$deleted);
 }
 public function clear():void {
  $this->checkToken();$this->allowDelete();$this->finish($this->workflow()->clear());
 }
}
