<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\System\DharmaShopMenu\Extension\DharmaShopMenu;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShopMenu {
            $plugin = new DharmaShopMenu((array) PluginHelper::getPlugin('system', 'dharmashopmenu'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
