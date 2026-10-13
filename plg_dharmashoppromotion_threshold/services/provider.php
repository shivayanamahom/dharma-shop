<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShopPromotion\Threshold\Extension\Threshold;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Threshold {
            $plugin = new Threshold((array) PluginHelper::getPlugin('dharmashoppromotion', 'threshold'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
