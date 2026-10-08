<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\System\DharmaShopFancybox\Extension\DharmaShopFancybox;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShopFancybox {
            $plugin = new DharmaShopFancybox((array) PluginHelper::getPlugin('system', 'dharmashopfancybox'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
