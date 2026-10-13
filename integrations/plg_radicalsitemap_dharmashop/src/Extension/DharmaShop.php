<?php

/**
 * @package     DharmaShop.RadicalSitemap
 * @copyright   (C) 2026 Dharma
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Dharma\Plugin\RadicalSitemap\DharmaShop\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Component\RadicalSitemap\Administrator\Helper\ScanHelper;

/** Adds public Dharma Shop catalogue URLs to Radical Sitemap scans. */
final class DharmaShop extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalSitemapGetScanSteps' => 'onGetScanSteps',
			'onScanDharmaShopCategories' => 'onScanCategories',
			'onScanDharmaShopProducts' => 'onScanProducts',
			'onScanDharmaShopLandings' => 'onScanLandings',
		];
	}

	public function onGetScanSteps(array &$steps): void
	{
		if (!ComponentHelper::isEnabled('com_dharmashop')) {
			return;
		}

		$steps[] = ['plugin' => 'dharmashop', 'event' => 'onScanDharmaShopCategories', 'title' => 'Dharma Shop - Categories'];
		$steps[] = ['plugin' => 'dharmashop', 'event' => 'onScanDharmaShopProducts', 'title' => 'Dharma Shop - Products'];
		if (ComponentHelper::isEnabled('com_dharmashop_landing')) {
			$steps[] = ['plugin' => 'dharmashop', 'event' => 'onScanDharmaShopLandings', 'title' => 'Dharma Shop - Landing pages'];
		}
	}

	public function onScanCategories(string $action, array &$data = [], bool|array $languages = false): bool|int|array
	{
		$db = $this->getDatabase();
		$extensions = ['com_dharmashop', 'com_dharmashop.brand', 'com_dharmashop.badge'];
		$query = $db->createQuery()->from($db->quoteName('#__categories', 'c'))
			->where($db->quoteName('c.extension') . ' IN (' . implode(',', array_map($db->quote(...), $extensions)) . ')')
			->where($db->quoteName('c.published') . ' = 1')->where($db->quoteName('c.access') . ' = 1')
			->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'parent') . ' WHERE ' . $db->quoteName('parent.extension') . ' = ' . $db->quoteName('c.extension')
				. ' AND ' . $db->quoteName('parent.lft') . ' <= ' . $db->quoteName('c.lft') . ' AND ' . $db->quoteName('parent.rgt') . ' >= ' . $db->quoteName('c.rgt')
				. ' AND (' . $db->quoteName('parent.published') . ' <> 1 OR ' . $db->quoteName('parent.access') . ' <> 1))');

		if ($action === 'total') {
			$query->clear('select')->select('COUNT(*)');
			$data['limit'] = 100;
			$data['total'] = (int) $db->setQuery($query)->loadResult();

			return $data['total'];
		}

		$lastId = (int) ($data['last'] ?? 0);
		$query->clear('select')->select($db->quoteName(['c.id', 'c.alias', 'c.extension', 'c.metadata']))
			->select($db->quoteName('c.modified_time', 'modified'))
			->where($db->quoteName('c.id') . ' > :last')->bind(':last', $lastId, ParameterType::INTEGER)
			->order($db->quoteName('c.id') . ' ASC');
		$rows = $db->setQuery($query, 0, max(1, (int) ($data['limit'] ?? 100)))->loadObjectList();

		if (!$rows) {
			return false;
		}

		$data['advance'] = count($rows);
		$result = [];
		foreach ($rows as $row) {
			$data['last'] = (int) $row->id;
			$view = match ($row->extension) {
				'com_dharmashop.brand' => 'brand',
				'com_dharmashop.badge' => 'badge',
				default => 'category',
			};
			$item = $this->newItem('index.php?option=com_dharmashop&view=' . $view . '&id=' . (int) $row->id, $row->modified);
			$item->sources[] = ['context' => 'com_dharmashop.' . $view, 'plugin' => 'dharmashop', 'component' => 'com_dharmashop', 'view' => $view, 'id' => (int) $row->id];
			$this->excludeRobots($item, $row->metadata, 'com_dharmashop.' . $view, $view, (int) $row->id);
			$result[] = $item;
		}

		return $result;
	}

	public function onScanProducts(string $action, array &$data = [], bool|array $languages = false): bool|int|array
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()->from($db->quoteName('#__dharmashop_products', 'p'))
			->innerJoin($db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('p.catid'))
			->where($db->quoteName('p.state') . ' = 1')->where($db->quoteName('c.published') . ' = 1')
			->where($db->quoteName('c.access') . ' = 1')
			->where($db->quoteName('c.extension') . ' IN (' . $db->quote('com_dharmashop') . ',' . $db->quote('com_dharmashop.brand') . ',' . $db->quote('com_dharmashop.badge') . ')');
		$query->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'parent') . ' WHERE ' . $db->quoteName('parent.extension') . ' = ' . $db->quoteName('c.extension')
			. ' AND ' . $db->quoteName('parent.lft') . ' <= ' . $db->quoteName('c.lft') . ' AND ' . $db->quoteName('parent.rgt') . ' >= ' . $db->quoteName('c.rgt')
			. ' AND (' . $db->quoteName('parent.published') . ' <> 1 OR ' . $db->quoteName('parent.access') . ' <> 1))');

		if ($action === 'total') {
			$query->clear('select')->select('COUNT(*)');
			$data['limit'] = 100;
			$data['total'] = (int) $db->setQuery($query)->loadResult();

			return $data['total'];
		}

		$lastId = (int) ($data['last'] ?? 0);
		$query->clear('select')->select($db->quoteName(['p.id', 'p.alias', 'p.catid', 'p.modified', 'p.metadata']))
			->where($db->quoteName('p.id') . ' > :last')->bind(':last', $lastId, ParameterType::INTEGER)
			->order($db->quoteName('p.id') . ' ASC');
		$rows = $db->setQuery($query, 0, max(1, (int) ($data['limit'] ?? 100)))->loadObjectList();

		if (!$rows) {
			return false;
		}

		$data['advance'] = count($rows);
		$result = [];
		foreach ($rows as $row) {
			$data['last'] = (int) $row->id;
			$item = $this->newItem('index.php?option=com_dharmashop&view=product&id=' . (int) $row->id . '&catid=' . (int) $row->catid, $row->modified);
			$item->sources[] = ['context' => 'com_dharmashop.product', 'plugin' => 'dharmashop', 'component' => 'com_dharmashop', 'view' => 'product', 'id' => (int) $row->id];
			$this->excludeRobots($item, $row->metadata, 'com_dharmashop.product', 'product', (int) $row->id);
			$result[] = $item;
		}

		return $result;
	}

	public function onScanLandings(string $action, array &$data = [], bool|array $languages = false): bool|int|array
	{
		if (!ComponentHelper::isEnabled('com_dharmashop_landing')) {
			return $action === 'total' ? 0 : false;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()->from($db->quoteName('#__dharmashop_landing_pages', 'a'))
			->where($db->quoteName('a.state') . ' = 1')->where($db->quoteName('a.broken') . ' = 0')->where($db->quoteName('a.access') . ' = 1')
			->where('(' . $db->quoteName('a.catid') . ' < 2 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'x')
				. ' WHERE ' . $db->quoteName('x.id') . ' = ' . $db->quoteName('a.catid') . ' AND ' . $db->quoteName('x.extension') . ' = ' . $db->quote('com_dharmashop')
				. ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'parent') . ' WHERE ' . $db->quoteName('parent.extension') . ' = ' . $db->quoteName('x.extension')
				. ' AND ' . $db->quoteName('parent.lft') . ' <= ' . $db->quoteName('x.lft') . ' AND ' . $db->quoteName('parent.rgt') . ' >= ' . $db->quoteName('x.rgt')
				. ' AND (' . $db->quoteName('parent.published') . ' <> 1 OR ' . $db->quoteName('parent.access') . ' <> 1))))');

		if ($action === 'total') {
			$query->clear('select')->select('COUNT(*)');
			$data['limit'] = 100;
			$data['total'] = (int) $db->setQuery($query)->loadResult();

			return $data['total'];
		}

		$lastId = (int) ($data['last'] ?? 0);
		$query->clear('select')->select($db->quoteName(['a.id', 'a.modified', 'a.robots']))
			->where($db->quoteName('a.id') . ' > :last')->bind(':last', $lastId, ParameterType::INTEGER)
			->order($db->quoteName('a.id') . ' ASC');
		$rows = $db->setQuery($query, 0, max(1, (int) ($data['limit'] ?? 100)))->loadObjectList();

		if (!$rows) {
			return false;
		}

		$data['advance'] = count($rows);
		$result = [];
		foreach ($rows as $row) {
			$data['last'] = (int) $row->id;
			$item = $this->newItem('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $row->id, $row->modified);
			$item->sources[] = ['context' => 'com_dharmashop_landing.landing', 'plugin' => 'dharmashop', 'component' => 'com_dharmashop_landing', 'view' => 'landing', 'id' => (int) $row->id];
			if (str_contains(strtolower((string) $row->robots), 'noindex')) {
				$item->excludes[] = ['context' => 'com_dharmashop_landing.landing', 'plugin' => 'dharmashop', 'component' => 'com_dharmashop_landing', 'view' => 'landing', 'id' => (int) $row->id, 'reason' => 'robots'];
			}
			$result[] = $item;
		}

		return $result;
	}

	private function newItem(string $loc, ?string $modified): object
	{
		$item = ScanHelper::getBlankItem();
		$item->loc = $loc;
		$item->language = '*';
		$item->lastmod = $modified && $modified !== '0000-00-00 00:00:00' ? $modified : null;

		return $item;
	}

	private function excludeRobots(object $item, ?string $metadata, string $context, string $view, int $id): void
	{
		$robots = (new Registry($metadata ?? ''))->get('robots', '');
		if (str_contains(strtolower((string) $robots), 'noindex')) {
			$item->excludes[] = ['context' => $context, 'plugin' => 'dharmashop', 'component' => 'com_dharmashop', 'view' => $view, 'id' => $id, 'reason' => 'robots'];
		}
	}
}
