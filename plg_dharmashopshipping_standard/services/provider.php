<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\DharmaShopShipping\Standard\Extension\Standard;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Standard {
            $plugin = new Standard((array) PluginHelper::getPlugin('dharmashopshipping', 'standard'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
