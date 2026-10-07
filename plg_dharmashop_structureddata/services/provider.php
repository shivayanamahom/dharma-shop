<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\DharmaShop\Structureddata\Extension\Structureddata;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Structureddata {
            $plugin = new Structureddata((array) PluginHelper::getPlugin('dharmashop', 'structureddata'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
