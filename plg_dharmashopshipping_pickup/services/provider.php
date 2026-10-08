<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\DharmaShopShipping\Pickup\Extension\Pickup;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Pickup {
            $plugin = new Pickup((array) PluginHelper::getPlugin('dharmashopshipping', 'pickup'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
