<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\CacheController;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Кэш данных фильтра (C84): определения полей, оценки плотности, числа найденных, значения и счётчики. Группа Joomla `com_dharmashop_filter`;
 * работает независимо от общего кэша сайта (включается явно), время жизни ограничено, сброс — `forget()` при записи каталога.
 */
final class FilterCache
{
    public const GROUP = 'com_dharmashop_filter';

    private static ?CacheController $controller = null;

    /** Минут; запись каталога сбрасывает группу, время жизни — страховка. */
    private static int $lifetime = 10;

    /**
     * Значение из кэша или результат вычисления. Ключ — любая строка (хэшируется); значение должно сериализоваться.
     *
     * @template T
     *
     * @param   callable(): T  $compute
     *
     * @return  T
     */
    public static function remember(string $key, callable $compute): mixed
    {
        $cache = self::controller();

        if ($cache === null) {
            return $compute();
        }

        $id = md5($key);
        $hit = self::read($cache, $id);

        if ($hit !== null) {
            return $hit['v'];
        }

        // Одно вычисление на ключ: после сброса кэша десятки параллельных запросов не считают одно и то же (штатный «callback»
        // держит блокировку только на чтение/запись, но не на вычисление). Остальные ждут готовый результат до ~3 с.
        $db = self::database();
        $lock = $db === null ? '' : 'dsfc_' . substr(md5($db->getPrefix() . $id), 0, 32);
        $state = $lock === '' ? -1 : self::acquire($db, $lock);
        $owned = $state === 1;

        try {
            if ($state === 0) {
                for ($i = 0; $i < 30 && !$owned; ++$i) {
                    usleep(100000);

                    if (($hit = self::read($cache, $id)) !== null) {
                        return $hit['v'];
                    }

                    $owned = self::acquire($db, $lock) === 1;
                }
            }

            if ($owned && ($hit = self::read($cache, $id)) !== null) {
                return $hit['v'];
            }

            $value = $compute();

            try {
                $cache->store(['v' => $value], $id);
            } catch (\Throwable $error) {
                // Кэш — оптимизация: сбой записи не ломает страницу.
            }

            return $value;
        } finally {
            if ($owned) {
                self::release($db, $lock);
            }
        }
    }

    /** @return array{v: mixed}|null */
    private static function read(CacheController $cache, string $id): ?array
    {
        try {
            $data = $cache->get($id);
        } catch (\Throwable $error) {
            return null;
        }

        return is_array($data) && array_key_exists('v', $data) ? $data : null;
    }

    private static function database(): ?DatabaseInterface
    {
        try {
            return Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $error) {
            return null;
        }
    }

    /** 1 — блокировка получена, 0 — занята другим запросом, −1 — блокировки недоступны (вычисляем без ожидания). */
    private static function acquire(DatabaseInterface $db, string $lock): int
    {
        try {
            return (int) $db->setQuery('SELECT GET_LOCK(' . $db->quote($lock) . ', 0)')->loadResult() === 1 ? 1 : 0;
        } catch (\Throwable $error) {
            return -1;
        }
    }

    private static function release(DatabaseInterface $db, string $lock): void
    {
        try {
            $db->setQuery('SELECT RELEASE_LOCK(' . $db->quote($lock) . ')')->loadResult();
        } catch (\Throwable $error) {
            // Блокировка снимется при закрытии соединения.
        }
    }

    /** Сбросить весь кэш фильтра (после записи товаров, полей, категорий, остатков). */
    public static function forget(): void
    {
        try {
            $cache = self::controller();
            $cache?->clean(self::GROUP);
        } catch (\Throwable $error) {
            // Кэш — оптимизация: сбой сброса не должен ломать сохранение.
        }
    }

    public static function setLifetime(int $minutes): void
    {
        self::$lifetime = max(1, $minutes);
        self::$controller = null;
    }

    private static function controller(): ?CacheController
    {
        if (self::$controller !== null) {
            return self::$controller;
        }

        try {
            /** @var CacheController $controller */
            $controller = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
                'defaultgroup' => self::GROUP,
                'caching' => true,
                'lifetime' => max(1, min(60, (int) ComponentHelper::getParams('com_dharmashop')->get('filter_cache_minutes', self::$lifetime))),
            ]);
            $controller->setCaching(true);

            return self::$controller = $controller;
        } catch (\Throwable $error) {
            return null;
        }
    }
}
