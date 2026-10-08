<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopCart\Site\Dispatcher;

use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Language\Text;
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
        $data['total'] = $summary['count'] > 0 ? $services->money()->price((string) $summary['subtotal']) : $services->money()->format((string) $summary['subtotal']);
        $data['cartUrl'] = Route::_('index.php?option=com_dharmashop&view=cart', false);
        $data['offcanvas'] = $data['params']->get('click_action', 'link') === 'offcanvas';
        $data['openOnAdd'] = (int) $data['params']->get('open_on_add', 1) === 1;

        if ($data['offcanvas']) {
            // Боковая панель: скрипт Bootstrap offcanvas и мини-корзина; подключается только в этом режиме.
            $document = $app->getDocument();
            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $wa->useScript('bootstrap.offcanvas')->useScript('com_dharmashop.mini-cart');

            // Оформление панели лежит в media модуля; его можно выключить и задать стили в шаблоне сайта.
            if ((int) $data['params']->get('panel_styles', 1) === 1) {
                $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_cart');
                $wa->useStyle('mod_dharmashop_cart.minicart');
            }
            $document->addScriptOptions('com_dharmashop.minicart', [
                'fragmentUrl' => Route::_('index.php?option=com_dharmashop&view=cart&layout=mini&format=raw'
                    . '&mc_image=' . (int) $data['params']->get('show_image', 1)
                    . '&mc_category=' . (int) $data['params']->get('show_category', 1)
                    . '&mc_sku=' . (int) $data['params']->get('show_sku', 0)
                    . '&mc_unit=' . (int) $data['params']->get('show_unit_price', 1), false),
                'updateUrl' => Route::_('index.php?option=com_dharmashop&task=cart.update&format=json', false),
                'removeUrl' => Route::_('index.php?option=com_dharmashop&task=cart.remove&format=json', false),
                'error' => Text::_('COM_DHARMASHOP_CART_ERROR_GENERIC'),
            ]);
        }

        return $data;
    }
}
