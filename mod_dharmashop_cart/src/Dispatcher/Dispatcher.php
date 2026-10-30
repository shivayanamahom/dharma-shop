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
        $cartService = $services->cart();
        $summary = $cart !== null ? $cartService->summary($cart) : ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0];
        $data['count'] = (int) $summary['count'];
        $displayTotal = $cart !== null
            ? $cartService->totals($cart, $cartService->lines($cart))['total']
            : (float) $summary['subtotal'];
        $data['total'] = $summary['count'] > 0 ? $services->money()->price((string) $displayTotal) : $services->money()->format((string) $displayTotal);
        $data['cartUrl'] = \Dharma\Component\DharmaShop\Site\Support\CheckoutMode::cartUrl();
        // Чекаут выводит серверный снимок состава: менять корзину нужно на её странице.
        // Проверяем разрешённый Joomla маршрут, чтобы правило работало и с SEF/пунктами меню.
        $input = $app->getInput();
        $isCheckout = $input->getCmd('option') === 'com_dharmashop' && $input->getCmd('view') === 'checkout';
        $data['cartDisabled'] = $isCheckout;
        $data['offcanvas'] = !$isCheckout && $data['params']->get('click_action', 'link') === 'offcanvas';
        $data['openOnAdd'] = !$isCheckout && (int) $data['params']->get('open_on_add', 0) === 1;

        if ($data['offcanvas']) {
            // Боковая панель: скрипт Bootstrap offcanvas и мини-корзина; подключается только в этом режиме.
            $document = $app->getDocument();
            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $wa->useScript('bootstrap.offcanvas')->useScript('com_dharmashop.mini-cart');
            \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::assets($document, ComponentHelper::getParams('com_dharmashop'));

            // Оформление панели лежит в media модуля; его можно выключить и задать стили в шаблоне сайта.
            if ((int) $data['params']->get('panel_styles', 1) === 1) {
                $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_cart');
                $wa->useStyle('mod_dharmashop_cart.minicart');
            }
            $document->addScriptOptions('com_dharmashop.minicart', [
                'fragmentUrl' => Route::_('index.php?option=com_dharmashop&view=cart&layout=mini&format=raw&mid=' . (int) $this->module->id, false),
                'updateUrl' => Route::_('index.php?option=com_dharmashop&task=cart.update&format=json', false),
                'removeUrl' => Route::_('index.php?option=com_dharmashop&task=cart.remove&format=json', false),
                'error' => Text::_('COM_DHARMASHOP_CART_ERROR_GENERIC'),
            ]);
        }

        return $data;
    }
}
