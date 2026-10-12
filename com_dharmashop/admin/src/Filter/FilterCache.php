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
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

\defined('_JEXEC') or die;

/**
 * Кэш данных фильтра (C84): определения полей, оценки плотности, числа найденных, значения и счётчики. Группа Joomla `com_dharmashop_filter`;
 * работает независимо от общего кэша сайта (включается явно), время жизни ограничено, сброс — `forget()` при записи каталога.
 */
final class FilterCache
{
    public const GROUP = 'com_dharmashop_filter';

    private static ?CallbackController $controller = null;

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

        // Контроллер «callback» хранит результат вычисления (в том числе false и пустые значения) и сам возвращает его при повторном вызове.
        return $cache->get($compute, [], md5($key));
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

    private static function controller(): ?CallbackController
    {
        if (self::$controller !== null) {
            return self::$controller;
        }

        try {
            /** @var CallbackController $controller */
            $controller = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', [
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
