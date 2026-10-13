<?php
declare(strict_types=1);
namespace Dharma\Plugin\Task\DharmaShopImport\Extension;

use Dharma\Component\DharmaShopImport\Administrator\Import\Access;
use Dharma\Component\DharmaShopImport\Administrator\Import\Runner;
use Dharma\Component\DharmaShopImport\Administrator\Import\Workflow;
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
 /** Сколько секунд одно срабатывание задачи выполняет шаги; остальное продолжится при следующем срабатывании. */
 private const SECONDS = 25.0;
 protected $autoloadLanguage=true;
 protected const TASKS_MAP=['dharmashop.import'=>['langConstPrefix'=>'PLG_TASK_DHARMASHOPIMPORT_RUN','method'=>'import','form'=>'import']];
 public static function getSubscribedEvents():array{return ['onTaskOptionsList'=>'advertiseRoutines','onExecuteTask'=>'standardRoutineHandler','onContentPrepareForm'=>'prepareTaskForm'];}
 /** Штатная подготовка формы задачи + значения по умолчанию после «В планировщик» в списке профилей. */
 public function prepareTaskForm(\Joomla\CMS\Event\Model\PrepareFormEvent $event):void {
  $this->enhanceTaskItemForm($event);
  $form=$event->getForm();$data=$event->getData();
  $app=Factory::getApplication();$choice=(array)$app->getUserState('com_dharmashop_import.schedule',[]);
  $saved=(int)(is_object($data)?($data->id??0):($data['id']??0));
  if($form->getName()!=='com_scheduler.task'||!$choice||$saved>0||(time()-(int)($choice['time']??0))>300||!$form->getField('profile_id','params')){return;}
  $form->setFieldAttribute('profile_id','default',(string)(int)$choice['profile'],'params');
  $form->setFieldAttribute('title','default',\Joomla\CMS\Language\Text::_('PLG_TASK_DHARMASHOPIMPORT_RUN_TITLE').': '.(string)$choice['title']);
 }
 protected function import(ExecuteTaskEvent $event):int {
  try{
   Factory::getApplication()->bootComponent('com_dharmashop_import');
   $params=$event->getArgument('params'); // параметры задачи — stdClass
   $get=static fn(string $k)=>is_object($params)?($params->$k??null):($params[$k]??null);
   Access::actAs($get('user_id')?:null);
   $workflow=new Workflow(Factory::getContainer()->get(DatabaseInterface::class));
   // Без человека: незавершённый запуск профиля продолжается, прерванный повторяется при следующем срабатывании; применение — только если включено в задаче.
   $result=Runner::run($workflow,(int)$get('profile_id'),(bool)(int)$get('apply'),self::SECONDS);
   $s=$result['summary'];
   $this->logTask('Dharma Shop import #'.$result['job'].': '.$result['state'].', create '.$s['create'].', update '.$s['update'].', unchanged '.$s['unchanged'].', errors '.$s['errors']);
   return match($result['state']){'done'=>Status::OK,'running'=>Status::WILL_RESUME,'checked'=>Status::OK,'busy'=>Status::NO_RUN,default=>Status::KNOCKOUT};
  }catch(\Throwable $e){$this->logTask('Dharma Shop import failed: '.get_class($e).' '.$e->getMessage());return Status::KNOCKOUT;}
 }
}
