<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Application\SiteApplication;
\defined('_JEXEC') or die;

/** Разрешения гостя выдаёт только успешный checkout в его серверной сессии. */
final class OrderAccess
{
    public static function remember(SiteApplication $app, int $id, int $userId): void
    {
        if ($id < 1 || $userId !== 0) return;
        $ids = (array) $app->getUserState('com_dharmashop.orders.guest', []);
        $ids[$id] = true;
        $app->setUserState('com_dharmashop.orders.guest', array_slice($ids, -50, null, true));
    }

    public static function allowsGuest(SiteApplication $app, int $id, int $userId): bool
    {
        if ($id < 1 || $userId !== 0) return false;
        $ids = (array) $app->getUserState('com_dharmashop.orders.guest', []);
        if (!empty($ids[$id])) return true;

        // Совместимость с уже оформленным заказом C73: повторная отправка не нужна.
        $receipt = (array) $app->getUserState('com_dharmashop.checkout.receipt', []);
        if ((int) ($receipt['id'] ?? 0) === $id && (int) ($receipt['user_id'] ?? -1) === 0
            && (int) ($receipt['time'] ?? 0) >= time() - 1800) {
            self::remember($app, $id, 0);
            return true;
        }
        return false;
    }
}
