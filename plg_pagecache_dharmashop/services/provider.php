<?php
declare(strict_types=1);

use Dharma\Plugin\PageCache\DharmaShop\Extension\DharmaShop;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

\defined('_JEXEC') or die;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): DharmaShop {
            $plugin = new DharmaShop((array) PluginHelper::getPlugin('pagecache', 'dharmashop'));
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
