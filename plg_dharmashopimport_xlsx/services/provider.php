<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\DharmaShopImport\Xlsx\Extension\Plugin;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
 public function register(Container $container): void {
  $container->set(PluginInterface::class, static function () {
   $plugin = new Plugin((array) PluginHelper::getPlugin('dharmashopimport', 'xlsx'));
   $plugin->setApplication(Factory::getApplication());
   return $plugin;
  });
 }
};
