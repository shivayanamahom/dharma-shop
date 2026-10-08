<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Подключает скрипт добавления в корзину (cart.js) с адресом действия и строками интерфейса. Вызывается только когда корзина
 * нужна странице: в режиме каталога (catalog_mode) страницы этот помощник не вызывают, и на сайт ничего не подключается.
 */
final class CartAssets
{
    public static function use(HtmlDocument $document): void
    {
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.cart');
        $document->addScriptOptions('com_dharmashop.cart', [
            'url' => Route::_('index.php?option=com_dharmashop&task=cart.add&format=json', false),
            'updateUrl' => Route::_('index.php?option=com_dharmashop&task=cart.update&format=json', false),
            'removeUrl' => Route::_('index.php?option=com_dharmashop&task=cart.remove&format=json', false),
            'cartUrl' => Route::_('index.php?option=com_dharmashop&view=cart', false),
        ]);

        foreach (['COM_DHARMASHOP_CART_ADDED_SHORT', 'COM_DHARMASHOP_CART_ERROR_GENERIC', 'COM_DHARMASHOP_CART_GO'] as $key) {
            Text::script($key);
        }
    }
}
