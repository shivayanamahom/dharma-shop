<?php
declare(strict_types=1);

namespace Dharma\Plugin\Console\DharmaShopImport\Extension;

use Dharma\Plugin\Console\DharmaShopImport\Console\ImportCommand;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Console\Application as ConsoleApplication;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Plugin extends CMSPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [ApplicationEvents::BEFORE_EXECUTE => 'registerCommands'];
	}

	public function registerCommands(ApplicationEvent $event): void
	{
		$app = Factory::getApplication();
		if (!$app instanceof ConsoleApplication) {
			return;
		}

		$app->getLanguage()->load('com_dharmashop_import', JPATH_ADMINISTRATOR);
		$app->addCommand(new ImportCommand());
	}
}
