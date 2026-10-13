<?php

/** Dharma Shop optional extension registry and activation controls. */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/** Optional extensions ship with the package but remain disabled until explicitly activated. */
final class AddonManager
{
	private const ADDONS = [
		'radicalsitemap_dharmashop' => [
			'title' => 'Dharma Shop для Radical Sitemap',
			'description' => 'Добавляет опубликованные страницы магазина в карту сайта Radical Sitemap.',
			'type' => 'plugin',
			'folder' => 'radicalsitemap',
			'element' => 'dharmashop',
			'component' => 'com_radicalsitemap',
		],
	];

	/** @return array<string, array<string, string>> */
	public function addons(): array
	{
		return self::ADDONS;
	}

	public function state(string $key): ?bool
	{
		$addon = self::ADDONS[$key] ?? null;
		if (!$addon) {
			return null;
		}

		$db = Factory::getDbo();
		$query = $db->createQuery()->select($db->quoteName('enabled'))->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote($addon['type']))
			->where($db->quoteName('folder') . ' = ' . $db->quote($addon['folder']))
			->where($db->quoteName('element') . ' = ' . $db->quote($addon['element']));
		$value = $db->setQuery($query)->loadResult();

		return $value === null ? null : (int) $value === 1;
	}

	public function setEnabled(string $key, bool $enabled): void
	{
		$addon = self::ADDONS[$key] ?? null;
		if (!$addon) {
			throw new \RuntimeException('COM_DHARMASHOP_ADDON_ERROR_UNKNOWN');
		}
		if ($enabled && !ComponentHelper::isEnabled($addon['component'])) {
			throw new \RuntimeException('COM_DHARMASHOP_ADDON_ERROR_DEPENDENCY');
		}
		if ($this->state($key) === null) {
			throw new \RuntimeException('COM_DHARMASHOP_ADDON_ERROR_NOT_INSTALLED');
		}

		$db = Factory::getDbo();
		$query = $db->createQuery()->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = ' . ($enabled ? '1' : '0'))
			->where($db->quoteName('type') . ' = ' . $db->quote($addon['type']))
			->where($db->quoteName('folder') . ' = ' . $db->quote($addon['folder']))
			->where($db->quoteName('element') . ' = ' . $db->quote($addon['element']));
		$db->setQuery($query)->execute();
	}
}
