<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\System\DharmaShopPositions\Extension\DharmaShopPositions;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShopPositions {
            $plugin = new DharmaShopPositions((array) PluginHelper::getPlugin('system', 'dharmashoppositions'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
