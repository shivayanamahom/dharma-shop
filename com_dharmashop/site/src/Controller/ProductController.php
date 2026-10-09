<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Controller;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
class ProductController extends BaseController
{
    protected $option='com_dharmashop';
    public function choose(): void
    {
        $app=Factory::getApplication();$sourceId=$this->input->getInt('id');
        $model=$this->getModel('Product','Site',['ignore_request'=>true]);
        $source=$model->getItem(); // Published source and category access checked by the usual model.
        $groupId=(int)($source->group_id ?? 0);
        $service=$app->bootComponent('com_dharmashop')->getServices()->groups();
        $group=$service->get($groupId);
        if(!$group || (int)$group->state!==1) {throw new \RuntimeException(Text::_('COM_DHARMASHOP_PRODUCT_NOT_FOUND'),404);}
        $levels=$app->getIdentity()->getAuthorisedViewLevels();
        foreach($service->axes($groupId) as $axis) {if((int)$axis->state !== 1 || !in_array((int)$axis->access,array_map('intval',$levels),true)) {throw new \RuntimeException(Text::_('COM_DHARMASHOP_PRODUCT_NOT_FOUND'),404);}}
        $values=array_map('intval',(array)$this->input->get('choice',[],'array'));
        $id=$service->resolve($groupId,$values);
        if($id) {
            $this->input->set('id',$id);
            try {$model->getItem();} catch(\Throwable $e) {if((int)$e->getCode()!==404) {throw $e;} $id=0;}
        }
        if(!$id) {$app->enqueueMessage(Text::_('COM_DHARMASHOP_GROUP_UNAVAILABLE'),'warning');$id=$sourceId;}
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=product&id='.$id,false));
    }
}
