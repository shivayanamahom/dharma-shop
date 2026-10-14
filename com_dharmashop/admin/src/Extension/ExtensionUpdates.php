<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

/**
 * Обновления частей Dharma Shop штатным механизмом Joomla: сайты обновлений («Система → Установка → Источники обновлений»),
 * таблица #__updates и модель com_installer UpdateModel — то же, что делает экран «Система → Обновление → Расширения».
 * Здесь только отбор расширений Dharma Shop и удобные операции для вкладки «Расширения» в настройках.
 */
final class ExtensionUpdates
{
	/** Источник обновлений Dharma Shop — один на все расширения (коллекция); его название задано в манифестах и не зависит от адреса. */
	private const SITE_NAME = 'Dharma Shop';

	private const ORDER = ['package' => 0, 'file' => 1, 'component' => 2, 'plugin' => 3, 'module' => 4];

	/** Названия значений «Минимальной стабильности» Joomla (число — как в com_installer). */
	public const CHANNELS = [0 => 'dev', 1 => 'alpha', 2 => 'beta', 3 => 'rc', 4 => 'stable'];

	/** @return list<array<string, mixed>> расширения с источником обновлений Dharma Shop: установленная версия и найденное обновление */
	public function rows(): array
	{
		$db = Factory::getDbo();
		$query = $db->createQuery()
			->select(['e.extension_id', 'e.type', 'e.element', 'e.folder', 'e.enabled', 'e.name', 'e.manifest_cache', 's.enabled AS site_enabled', 'u.update_id', 'u.version AS available'])
			->from($db->quoteName('#__extensions', 'e'))
			->join('INNER', $db->quoteName('#__update_sites_extensions', 'se') . ' ON se.extension_id = e.extension_id')
			->join('INNER', $db->quoteName('#__update_sites', 's') . ' ON s.update_site_id = se.update_site_id AND s.name = ' . $db->quote(self::SITE_NAME))
			->join('LEFT', $db->quoteName('#__updates', 'u') . ' ON u.extension_id = e.extension_id AND u.update_site_id = s.update_site_id');
		$rows = [];
		$language = Factory::getApplication()->getLanguage();

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$manifest = json_decode((string) $row['manifest_cache'], true) ?: [];
			$this->loadLanguage($language, $row);
			$installed = (string) ($manifest['version'] ?? '');
			$available = (string) ($row['available'] ?? '');
			$rows[] = [
				'id' => (int) $row['extension_id'],
				'type' => (string) $row['type'],
				'element' => (string) $row['element'],
				'folder' => (string) $row['folder'],
				'enabled' => (int) $row['enabled'] === 1,
				'siteEnabled' => (int) $row['site_enabled'] === 1,
				'title' => $this->title($row, $manifest),
				'installed' => $installed,
				'available' => $available !== '' && version_compare($available, $installed, '>') ? $available : '',
				'updateId' => (int) ($row['update_id'] ?? 0),
			];
		}

		usort($rows, static fn (array $a, array $b): int => [self::ORDER[$a['type']] ?? 9, $a['folder'], $a['title']] <=> [self::ORDER[$b['type']] ?? 9, $b['folder'], $b['title']]);

		return $rows;
	}

	/** Название расширения: из языкового файла; у пакета и файлового набора, у которых нет собственной строки, — общее название. */
	private function title(array $row, array $manifest): string
	{
		$name = (string) ($manifest['name'] ?? $row['name']);
		$text = Text::_($name);

		if ($text === $name && $row['type'] === 'package') {
			return Text::_('COM_DHARMASHOP_EXT_TITLE_PACKAGE');
		}

		if ($text === $name && $row['type'] === 'file') {
			return Text::_('COM_DHARMASHOP_EXT_TITLE_FILES');
		}

		return $text;
	}

	/** Читает языковой файл расширения, чтобы показать его название, а не языковую константу. */
	private function loadLanguage(object $language, array $row): void
	{
		$base = match ($row['type']) {
			'plugin' => 'plg_' . $row['folder'] . '_' . $row['element'],
			default => $row['element'],
		};
		$language->load($base . '.sys', JPATH_ADMINISTRATOR);
		$language->load($base . '.sys', JPATH_ADMINISTRATOR . '/components/' . $row['element']);
	}

	/** Текущая «Минимальная стабильность» Joomla (одна настройка на все расширения: Установка → Расширения → Параметры). */
	public function stability(): int
	{
		return (int) ComponentHelper::getParams('com_installer')->get('minimum_stability', 4);
	}

	public function setStability(int $value): void
	{
		if (!isset(self::CHANNELS[$value])) {
			throw new \RuntimeException('COM_DHARMASHOP_EXT_CHANNEL_INVALID');
		}

		$table = Table::getInstance('extension');
		if (!$table->load(['element' => 'com_installer', 'type' => 'component'])) {
			throw new \RuntimeException('COM_DHARMASHOP_EXT_CHANNEL_INVALID');
		}

		$params = new Registry($table->params);
		$params->set('minimum_stability', (string) $value);
		$table->params = (string) $params;
		$table->store();
		Factory::getCache()->clean('_system');
	}

	/**
	 * Поиск обновлений штатным поиском Joomla. Источник один (коллекция всех расширений), поэтому проверка — один небольшой запрос.
	 * Прежние отдельные источники по расширениям («Dharma Shop: имя», версии до 0.1.4) удаляются: каждый из них добавлял запрос.
	 *
	 * @return int число найденных обновлений
	 */
	public function check(): int
	{
		$this->removeLegacySites();
		$this->probe();
		$ids = array_column($this->rows(), 'id');

		if ($ids) {
			@set_time_limit(120);
			$this->model()->findUpdates($ids, 0, $this->stability());
		}

		return count(array_filter($this->rows(), static fn (array $r): bool => $r['available'] !== ''));
	}

	/** Быстрая проверка доступности источника (10 секунд): без неё штатный поиск лишь предупредил бы и ответил «обновлений нет». */
	private function probe(): void
	{
		$db = Factory::getDbo();
		$location = trim((string) $db->setQuery($db->createQuery()->select('location')->from($db->quoteName('#__update_sites'))->where('name = ' . $db->quote(self::SITE_NAME)), 0, 1)->loadResult());

		if ($location === '') {
			throw new \RuntimeException('COM_DHARMASHOP_EXT_NO_SITES');
		}

		try {
			$status = HttpFactory::getHttp(new Registry(['follow_location' => true]), ['curl', 'stream'])->get($location, [], 10)->getStatusCode();
		} catch (\Throwable) {
			$status = 0;
		}

		if ($status !== 200) {
			throw new \RuntimeException('COM_DHARMASHOP_EXT_FEED_UNAVAILABLE');
		}
	}

	/** Удаляет источники обновлений прежнего вида (по одному на расширение). */
	public function removeLegacySites(): void
	{
		$db = Factory::getDbo();
		$ids = $db->setQuery($db->createQuery()->select('update_site_id')->from($db->quoteName('#__update_sites'))->where('name LIKE ' . $db->quote('Dharma Shop: %')))->loadColumn();

		if (!$ids) {
			return;
		}

		$list = implode(',', array_map('intval', $ids));
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__update_sites_extensions') . ' WHERE update_site_id IN (' . $list . ')')->execute();
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__updates') . ' WHERE update_site_id IN (' . $list . ')')->execute();
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__update_sites') . ' WHERE update_site_id IN (' . $list . ')')->execute();
	}

	/**
	 * Обновление выбранных записей #__updates средствами Joomla (скачивание, проверка контрольной суммы, установка).
	 *
	 * @param list<int> $updateIds
	 */
	public function update(array $updateIds): bool
	{
		$model = $this->model();
		$model->update($updateIds, $this->stability());

		return (bool) $model->getState('result');
	}

	/** Идентификаторы обновлений «обновить всё»: пакет целиком, если он обновился (он включает все части), иначе части в порядке «компоненты → плагины → модули». @return list<int> */
	public function allUpdateIds(): array
	{
		$pending = array_values(array_filter($this->rows(), static fn (array $r): bool => $r['available'] !== '' && $r['updateId'] > 0));
		foreach ($pending as $row) {
			if ($row['type'] === 'package') {
				return [$row['updateId']];
			}
		}

		return array_column($pending, 'updateId');
	}

	/** Модель обновлений com_installer (то же, что использует штатный экран). */
	private function model(): object
	{
		return Factory::getApplication()->bootComponent('com_installer')->getMVCFactory()->createModel('Update', 'Administrator', ['ignore_request' => true]);
	}
}
