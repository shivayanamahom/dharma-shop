<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopCart\Site\Dispatcher;

use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Значок корзины: число позиций и сумма. Корзину не создаёт (cookie ставится только при добавлении товара). В режиме каталога
 * магазина (catalog_mode) модуль ничего не выводит и ничего не подключает.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        if (!class_exists(CartSession::class) || (int) ComponentHelper::getParams('com_dharmashop')->get('catalog_mode', 0) === 1) {
            return;
        }

        parent::dispatch();
    }

    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $app = $this->getApplication();
        $services = $app->bootComponent('com_dharmashop')->getServices();
        $session = new CartSession($services->cart(), $app);
        $cart = $session->current();
        $summary = $cart !== null ? $services->cart()->summary($cart) : ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0];
        $data['count'] = (int) $summary['count'];
        $data['total'] = $services->money()->format((string) $summary['subtotal']);
        $data['cartUrl'] = Route::_('index.php?option=com_dharmashop&view=cart', false);

        return $data;
    }
}
