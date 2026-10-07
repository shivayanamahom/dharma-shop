<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShopField\Basic\Extension\Basic;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Basic {
            $plugin = new Basic((array) PluginHelper::getPlugin('dharmashopfield', 'basic'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
