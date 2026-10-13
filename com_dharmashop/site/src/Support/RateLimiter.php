<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Ограничение частоты действий без новых таблиц: счётчик в файловом кэше Joomla по фиксированным окнам времени. Субъект — адрес
 * клиента (`REMOTE_ADDR`; за прокси задайте реальный адрес на уровне веб-сервера) или нормализованный e-mail. Сбой кэша не
 * блокирует магазин (limiter «открыт»): это защита от потока заказов и платных запросов, а не граница безопасности.
 */
final class RateLimiter
{
    private const GROUP = 'com_dharmashop_rate';

    private static ?OutputController $cache = null;

    /** Засчитывает обращение и сообщает, укладывается ли субъект в `$limit` за окно `$window` секунд. `$limit < 1` — без ограничения. */
    public static function allow(string $bucket, string $subject, int $limit, int $window): bool
    {
        if ($limit < 1 || $subject === '') {
            return true;
        }

        $cache = self::cache($window);

        if ($cache === null) {
            return true;
        }

        $id = sha1($bucket . '|' . mb_strtolower($subject) . '|' . intdiv(time(), max(1, $window)));

        try {
            $data = $cache->get($id);
            $count = is_array($data) ? (int) ($data['n'] ?? 0) : 0;

            if ($count >= $limit) {
                return false;
            }

            $cache->store(['n' => $count + 1], $id);
        } catch (\Throwable $error) {
            return true;
        }

        return true;
    }

    /** Адрес клиента для счётчиков. */
    public static function clientAddress(): string
    {
        return (string) Factory::getApplication()->getInput()->server->getString('REMOTE_ADDR', '');
    }

    private static function cache(int $window): ?OutputController
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            /** @var OutputController $cache */
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
                'defaultgroup' => self::GROUP,
                'caching' => true,
                'lifetime' => max(2, intdiv($window, 60) + 2),
            ]);
            $cache->setCaching(true);

            return self::$cache = $cache;
        } catch (\Throwable $error) {
            return null;
        }
    }
}
