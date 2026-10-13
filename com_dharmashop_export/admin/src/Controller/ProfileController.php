<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Controller;
use Dharma\Component\DharmaShopExport\Administrator\Export\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class ProfileController extends \Joomla\CMS\MVC\Controller\FormController {
 use RequiresActionTrait;
 protected function requiredActions():array {return ['*'=>'core.manage','saveandrun'=>'dharmashop.export.run'];}
 protected $option='com_dharmashop_export';protected $view_list='profiles';protected $text_prefix='COM_DHARMASHOP_EXPORT';
 public function __construct($config=[], $factory=null, $app=null, $input=null){parent::__construct($config,$factory,$app,$input);$this->registerTask('saveandrun','save');}
 /** «Добавить в планировщик»: запоминает профиль и открывает форму новой задачи Joomla; плагин задачи подставляет профиль и название (одноразово, не дольше пяти минут). */
 public function schedule():void {
  $this->checkToken('get');$id=$this->input->getInt('id');
  if(!$this->app->getIdentity()->authorise('core.create','com_scheduler')||!PluginHelper::isEnabled('task','dharmashopexport')){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}
  $title=(string)Factory::getContainer()->get(DatabaseInterface::class)->setQuery('SELECT title FROM #__dharmashop_export_profiles WHERE id='.$id)->loadResult();
  if($title===''){throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'),404);}
  $this->app->setUserState('com_dharmashop_export.schedule',['profile'=>$id,'title'=>$title,'time'=>time()]);
  $this->setRedirect('index.php?option=com_scheduler&task=task.add&type=dharmashop.export');
 }
 protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model,$validData=[]) {
  if($this->getTask()==='saveandrun'){
   try{$id=(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->start((int)$model->getState('profile.id'),(int)$this->app->getIdentity()->id);$this->setRedirect('index.php?option='.$this->option.'&view=job&id='.$id);}
   catch(\Throwable $e){$this->setMessage(Text::_($e->getMessage()),'error');}
  }
 }
}
