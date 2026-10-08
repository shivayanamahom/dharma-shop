<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Способ доставки: нативные сохранение, отмена и блокировка формы Joomla; доступ — право dharmashop.shipping.manage. */
class ShippingController extends FormController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'shippings';

    protected function allowAdd($data = [])
    {
        return $this->app->getIdentity()->authorise('dharmashop.shipping.manage', $this->option) && parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'id')
    {
        return $this->app->getIdentity()->authorise('dharmashop.shipping.manage', $this->option) && parent::allowEdit($data, $key);
    }
}
