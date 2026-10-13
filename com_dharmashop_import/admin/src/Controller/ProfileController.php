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
    public function __construct($config=[],$factory=null,$app=null,$input=null){parent::__construct($config,$factory,$app,$input);$this->registerTask('saveandcheck','save');}
    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model,$validData=[]){
        if($this->getTask()==='saveandcheck'){
            try{$id=(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->start((int)$model->getState('profile.id'));$this->setRedirect('index.php?option='.$this->option.'&view=job&run=1&id='.$id);}
            catch(\Throwable $e){$this->setMessage(Text::_(Workflow::message($e)),'error');$this->setRedirect('index.php?option='.$this->option.'&view=profile&layout=edit&id='.(int)$model->getState('profile.id'));}
        }
    }
}
