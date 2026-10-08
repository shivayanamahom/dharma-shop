<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShop\ImageResize\Extension\ImageResize;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): ImageResize {
            $plugin = new ImageResize((array) PluginHelper::getPlugin('dharmashop', 'imageresize'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
