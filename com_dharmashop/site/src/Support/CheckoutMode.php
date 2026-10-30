<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Режим оформления заказа (C100): «в два шага» (страницы корзины и оформления) или «в один шаг» (макет `onepage`). */
final class CheckoutMode
{
    public const TWO_STEPS = 'two_steps';
    public const ONE_STEP = 'one_step';

    /** Общая настройка магазина `checkout_mode`; макет `onepage` пункта меню оформления выбирается отдельно и действует только на нём. */
    public static function oneStep(): bool
    {
        return (string) ComponentHelper::getParams('com_dharmashop')->get('checkout_mode', self::TWO_STEPS) === self::ONE_STEP;
    }

    /** Адрес «корзины» для ссылок значка, тоста и мини-корзины: в режиме «в один шаг» это страница оформления. */
    public static function cartUrl(): string
    {
        return Route::_('index.php?option=com_dharmashop&view=' . (self::oneStep() ? 'checkout' : 'cart'), false);
    }
}
