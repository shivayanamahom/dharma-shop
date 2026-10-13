<?php

/** Dharma Shop optional add-on actions. */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Extension\AddonManager;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class AddonsController extends BaseController
{
	protected $option = 'com_dharmashop';

	public function enable(): void
	{
		$this->run(true);
	}

	public function disable(): void
	{
		$this->run(false);
	}

	private function run(bool $enabled): void
	{
		$this->checkToken();
		$url = Route::_('index.php?option=com_config&view=component&component=com_dharmashop', false);

		if (!$this->app->getIdentity()->authorise('core.admin', 'com_dharmashop')) {
			$this->setRedirect($url, Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return;
		}

		try {
			$manager = new AddonManager();
			$key = $this->input->getCmd('addon');
			$manager->setEnabled($key, $enabled);
			$this->setRedirect($url, Text::_($enabled ? 'COM_DHARMASHOP_ADDON_ENABLED_SUCCESS' : 'COM_DHARMASHOP_ADDON_DISABLED_SUCCESS'), 'message');
		} catch (\Throwable $e) {
			$message = str_starts_with($e->getMessage(), 'COM_DHARMASHOP_') ? Text::_($e->getMessage()) : Text::_('COM_DHARMASHOP_ADDON_ERROR_GENERIC');
			$this->setRedirect($url, $message, 'error');
		}
	}
}
