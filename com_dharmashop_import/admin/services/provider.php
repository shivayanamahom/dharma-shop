<?php
/** Dharma Shop — импорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

\defined('_JEXEC') or die;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
 public function register(Container $c): void {
  $c->registerServiceProvider(new MVCFactory('\\Dharma\\Component\\DharmaShopImport'));
  $c->registerServiceProvider(new ComponentDispatcherFactory('\\Dharma\\Component\\DharmaShopImport'));
  $c->set(ComponentInterface::class, static function(Container $c) {
   $component=new MVCComponent($c->get(ComponentDispatcherFactoryInterface::class));
   $component->setMVCFactory($c->get(MVCFactoryInterface::class));return $component;
  });
 }
};
