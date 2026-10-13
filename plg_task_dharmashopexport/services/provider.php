<?php
\defined('_JEXEC') or die;
use Dharma\Plugin\Task\DharmaShopExport\Extension\Plugin;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
 public function register(Container $c):void {$c->set(PluginInterface::class,static function(){$p=new Plugin((array)PluginHelper::getPlugin('task','dharmashopexport'));$p->setApplication(Factory::getApplication());return $p;});}
};
