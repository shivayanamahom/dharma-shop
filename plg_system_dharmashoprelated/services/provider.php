<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\System\DharmaShopRelated\Extension\DharmaShopRelated;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShopRelated {
            $plugin = new DharmaShopRelated((array) PluginHelper::getPlugin('system', 'dharmashoprelated'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
