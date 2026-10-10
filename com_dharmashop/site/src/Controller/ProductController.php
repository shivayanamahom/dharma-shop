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
        $app=Factory::getApplication();
        $sourceId=$this->input->getInt('id');
        $model=$this->getModel('Product','Site',['ignore_request'=>true]);
        $id=$model->selectChoice((array)$this->input->get('choice',[],'array'));
        $resolve=$this->input->getInt('product_choice_resolve')===1;
        if ($resolve || (!$id && $this->input->getInt('product_choice_ajax')===1)) {
            $app->allowCache(false);
            if (!$id) { $app->setHeader('status','409',true); }
            $app->setHeader('Content-Type','application/json; charset=utf-8',true);
            $app->setHeader('Cache-Control','no-store',true);
            $app->setHeader('X-Robots-Tag','noindex',true);
            $app->sendHeaders();
            echo json_encode($id
                ? ['success'=>true,'url'=>Route::_('index.php?option=com_dharmashop&view=product&id='.$id,false)]
                : ['success'=>false,'reason'=>'unavailable','message'=>Text::_('COM_DHARMASHOP_PRODUCT_CHOICE_UNAVAILABLE')], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $app->close();
        }
        if(!$id) {$app->enqueueMessage(Text::_('COM_DHARMASHOP_PRODUCT_CHOICE_UNAVAILABLE'),'warning');$id=$sourceId;}
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=product&id='.$id,false));
    }
}
