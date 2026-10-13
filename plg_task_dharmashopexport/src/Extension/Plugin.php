<?php
declare(strict_types=1);
namespace Dharma\Plugin\Task\DharmaShopExport\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
final class Plugin extends CMSPlugin implements SubscriberInterface {
 use TaskPluginTrait;
 protected $autoloadLanguage=true;
 protected const TASKS_MAP=['dharmashop.export'=>['langConstPrefix'=>'PLG_TASK_DHARMASHOPEXPORT_RUN','method'=>'export','form'=>'export']];
 public static function getSubscribedEvents():array{return ['onTaskOptionsList'=>'advertiseRoutines','onExecuteTask'=>'standardRoutineHandler','onContentPrepareForm'=>'prepareTaskForm'];}
 /** Штатная подготовка формы задачи + значения по умолчанию после «Добавить в планировщик» в списке профилей. */
 public function prepareTaskForm(\Joomla\CMS\Event\Model\PrepareFormEvent $event):void {
  $this->enhanceTaskItemForm($event);
  $form=$event->getForm();$data=$event->getData();
  $app=Factory::getApplication();$choice=(array)$app->getUserState('com_dharmashop_export.schedule',[]);
  $saved=(int)(is_object($data)?($data->id??0):($data['id']??0));
  if($form->getName()!=='com_scheduler.task'||!$choice||$saved>0||(time()-(int)($choice['time']??0))>300||!$form->getField('profile_id','params')){return;}
  $form->setFieldAttribute('profile_id','default',(string)(int)$choice['profile'],'params');
  $form->setFieldAttribute('title','default',\Joomla\CMS\Language\Text::_('PLG_TASK_DHARMASHOPEXPORT_RUN_TITLE').': '.(string)$choice['title']);
 }
 protected function export(ExecuteTaskEvent $event):int {
  try{
   Factory::getApplication()->bootComponent('com_dharmashop_export');
   $params=$event->getArgument('params');$profile=(int)(is_object($params)?($params->profile_id??0):($params['profile_id']??0)); // параметры задачи — stdClass
   $workflow=new \Dharma\Component\DharmaShopExport\Administrator\Export\Workflow(Factory::getContainer()->get(DatabaseInterface::class));
   // Запуск без человека: незавершённый запуск профиля продолжается, прерванный по ошибке повторяется при следующем срабатывании.
   $id=$workflow->start($profile);
   if($workflow->job($id)['state']==='failed'){$workflow->change($id,'resume');}
   $job=$workflow->step($id);
   $this->logTask('Dharma Shop export #'.$id.': '.$job['state'].', rows '.$job['written']);
   return match($job['state']){'done'=>Status::OK,'running'=>Status::WILL_RESUME,'failed'=>Status::KNOCKOUT,default=>Status::NO_RUN};
  }catch(\Throwable $e){$this->logTask('Dharma Shop export failed: '.get_class($e).' '.$e->getMessage());return Status::KNOCKOUT;}
 }
}
