<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Controller;
use Joomla\CMS\MVC\Controller\FormController;
\defined('_JEXEC') or die;
class StatusController extends FormController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'statuses';
    protected function allowAdd($data = []) { return $this->app->getIdentity()->authorise('dharmashop.statuses.manage',$this->option) && parent::allowAdd($data); }
    protected function allowEdit($data = [], $key = 'id') { return $this->app->getIdentity()->authorise('dharmashop.statuses.manage',$this->option) && parent::allowEdit($data,$key); }
}
