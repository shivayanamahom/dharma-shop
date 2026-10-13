<?php

/** Dharma Shop optional extensions field. */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Extension\AddonManager;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

final class AddonsField extends FormField
{
	protected $type = 'Addons';

	protected function getLabel(): string
	{
		return '';
	}

	protected function getInput(): string
	{
		$manager = new AddonManager();
		$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$rows = '';

		foreach ($manager->addons() as $key => $addon) {
			$state = $manager->state($key);
			$dependency = ComponentHelper::isEnabled($addon['component']);
			if ($state === null) {
				$status = '<span class="badge text-bg-secondary">' . $escape(Text::_('COM_DHARMASHOP_ADDON_NOT_INSTALLED')) . '</span>';
				$action = '<span class="small text-body-secondary">' . $escape(Text::_('COM_DHARMASHOP_ADDON_PACKAGE_UPDATE')) . '</span>';
			} elseif ($state) {
				$status = $dependency
					? '<span class="badge text-bg-success">' . $escape(Text::_('COM_DHARMASHOP_ADDON_ENABLED')) . '</span>'
					: '<span class="badge text-bg-warning">' . $escape(Text::_('COM_DHARMASHOP_ADDON_NEEDS_SITEMAP')) . '</span>';
				$action = '<button type="submit" class="btn btn-sm btn-outline-secondary" formaction="index.php?option=com_dharmashop&addons_action=disable&addon=' . rawurlencode($key) . '" formnovalidate>'
					. $escape(Text::_('COM_DHARMASHOP_ADDON_DISABLE')) . '</button>';
			} else {
				$status = '<span class="badge text-bg-secondary">' . $escape(Text::_('COM_DHARMASHOP_ADDON_DISABLED')) . '</span>';
				$action = '<button type="submit" class="btn btn-sm btn-primary" formaction="index.php?option=com_dharmashop&addons_action=enable&addon=' . rawurlencode($key) . '" formnovalidate' . ($dependency ? '' : ' disabled') . '>'
					. $escape(Text::_('COM_DHARMASHOP_ADDON_ENABLE')) . '</button>';
			}

			$rows .= '<tr><th scope="row">' . $escape($addon['title']) . '<div class="small text-body-secondary fw-normal">' . $escape($addon['description']) . '</div></th>'
				. '<td>' . $status . '</td><td>' . $action . '</td></tr>';
		}

		return '<p>' . $escape(Text::_('COM_DHARMASHOP_ADDON_HELP')) . '</p><div class="table-responsive"><table class="table align-middle">'
			. '<thead><tr><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_ADDON_COL_EXTENSION')) . '</th><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_ADDON_COL_STATE')) . '</th><th scope="col">' . $escape(Text::_('COM_DHARMASHOP_ADDON_COL_ACTION')) . '</th></tr></thead>'
			. '<tbody>' . $rows . '</tbody></table></div>';
	}
}
