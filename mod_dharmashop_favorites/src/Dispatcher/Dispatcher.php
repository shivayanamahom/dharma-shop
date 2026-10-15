<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopFavorites\Site\Dispatcher;

use Dharma\Component\DharmaShop\Site\Support\FavoritesSession;
use Dharma\Component\DharmaShop\Site\Support\FavoritesSettings;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $app = $this->getApplication();
        $app->allowCache(false);

        if (!FavoritesSettings::enabled()) {
            $data['enabled'] = false;

            return $data;
        }

        $session = new FavoritesSession($app->bootComponent('com_dharmashop')->getServices()->favorites(), $app);
        $list = $session->current();
        $data['count'] = $list ? $session->service()->count((int) $list->id) : 0;
        $data['enabled'] = true;
        $data['favoriteIcon'] = FavoritesSettings::renderIcon(22);
        $data['favoritesUrl'] = Route::_('index.php?option=com_dharmashop&view=favorites', false);
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.favorites');
        $app->getDocument()->addScriptOptions('com_dharmashop.favorites', [
            'toggleUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.toggle&format=json', false),
            'tokenName' => Session::getFormToken(),
            'removeUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.remove&format=json', false),
            'clearUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.clear&format=json', false),
            'error' => \Joomla\CMS\Language\Text::_('COM_DHARMASHOP_FAVORITES_ERROR_GENERIC'),
        ]);
        return $data;
    }
}
