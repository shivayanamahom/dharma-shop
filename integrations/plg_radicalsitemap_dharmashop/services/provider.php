<?php

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Plugin\RadicalSitemap\DharmaShop\Extension\DharmaShop;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(PluginInterface::class, static function (Container $container): PluginInterface {
			$plugin = new DharmaShop(
				$container->get(DispatcherInterface::class),
				(array) PluginHelper::getPlugin('radicalsitemap', 'dharmashop')
			);
			$plugin->setApplication(Factory::getApplication());
			$plugin->setDatabase($container->get(DatabaseDriver::class));

			return $plugin;
		});
	}
};
