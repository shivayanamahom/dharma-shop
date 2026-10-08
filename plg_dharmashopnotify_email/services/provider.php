<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShopNotify\Email\Extension\Email;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): Email {
            $plugin = new Email((array) PluginHelper::getPlugin('dharmashopnotify', 'email'), $container->get(DatabaseInterface::class), $container->get(MailerFactoryInterface::class));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
