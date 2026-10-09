<?php
\defined('_JEXEC') or die;

use Dharma\Plugin\DharmaShopNotify\Telegram\Extension\Telegram;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): Telegram {
            $plugin = new Telegram((array) PluginHelper::getPlugin('dharmashopnotify', 'telegram'), $container->get(DatabaseInterface::class));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
