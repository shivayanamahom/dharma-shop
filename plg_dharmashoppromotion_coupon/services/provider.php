<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShopPromotion\Coupon\Extension\Coupon;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (): Coupon {
            $plugin = new Coupon((array)PluginHelper::getPlugin('dharmashoppromotion','coupon'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
