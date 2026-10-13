<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Controller;
use Dharma\Component\DharmaShopImport\Administrator\Import\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class ProfileController extends \Joomla\CMS\MVC\Controller\FormController
{
    use RequiresActionTrait;
    protected $option='com_dharmashop_import';protected $view_list='profiles';protected $text_prefix='COM_DHARMASHOP_IMPORT';
    protected function requiredActions():array{return ['*'=>'core.manage'];}
    /** «В планировщик»: запоминает профиль и открывает форму новой задачи Joomla; плагин задачи подставляет профиль и название (одноразово, не дольше пяти минут). */
    public function schedule():void{
        $this->checkToken('get');$id=$this->input->getInt('id');
        if(!$this->app->getIdentity()->authorise('core.create','com_scheduler')||!\Joomla\CMS\Plugin\PluginHelper::isEnabled('task','dharmashopimport')){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}
        $title=(string)Factory::getContainer()->get(DatabaseInterface::class)->setQuery('SELECT title FROM #__dharmashop_import_profiles WHERE id='.$id)->loadResult();
        if($title===''){throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'),404);}
        $this->app->setUserState('com_dharmashop_import.schedule',['profile'=>$id,'title'=>$title,'time'=>time()]);
        $this->setRedirect('index.php?option=com_scheduler&task=task.add&type=dharmashop.import');
    }
    public function __construct($config=[],$factory=null,$app=null,$input=null){parent::__construct($config,$factory,$app,$input);$this->registerTask('saveandcheck','save');}
    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model,$validData=[]){
        if($this->getTask()==='saveandcheck'){
            try{$id=(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->start((int)$model->getState('profile.id'));$this->setRedirect('index.php?option='.$this->option.'&view=job&run=1&id='.$id);}
            catch(\Throwable $e){$this->setMessage(Text::_(Workflow::message($e)),'error');$this->setRedirect('index.php?option='.$this->option.'&view=profile&layout=edit&id='.(int)$model->getState('profile.id'));}
        }
    }
}
