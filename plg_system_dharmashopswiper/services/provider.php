<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\System\DharmaShopSwiper\Extension\DharmaShopSwiper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShopSwiper {
            $plugin = new DharmaShopSwiper((array) PluginHelper::getPlugin('system', 'dharmashopswiper'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
