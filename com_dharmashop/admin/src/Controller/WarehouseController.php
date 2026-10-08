<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Склад: нативные сохранение, отмена и блокировка формы Joomla; доступ — право dharmashop.warehouses.manage. */
class WarehouseController extends FormController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'warehouses';

    protected function allowAdd($data = [])
    {
        return $this->app->getIdentity()->authorise('dharmashop.warehouses.manage', $this->option) && parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'id')
    {
        return $this->app->getIdentity()->authorise('dharmashop.warehouses.manage', $this->option) && parent::allowEdit($data, $key);
    }
}
