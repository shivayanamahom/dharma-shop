<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/** Публикация, порядок и удаление складов через штатный AdminController. */
class WarehousesController extends AdminController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Warehouse', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise('dharmashop.warehouses.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }
}
