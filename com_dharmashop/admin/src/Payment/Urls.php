<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Payment;

use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Адреса сайта для платежа: страница оплаты, страница заказа и обратные вызовы шлюза (абсолютные ссылки). */
final class Urls
{
    public function __construct(private readonly int $orderId, private readonly int $methodId, private readonly string $key)
    {
    }

    /** Страница оплаты на сайте. */
    public function pay(): string
    {
        return self::absolute('index.php?option=com_dharmashop&view=payment&id=' . $this->orderId . '&key=' . $this->key);
    }

    /** Страница заказа (для возврата покупателя). */
    public function order(): string
    {
        return self::absolute('index.php?option=com_dharmashop&view=order&id=' . $this->orderId . '&key=' . $this->key);
    }

    /**
     * Обратный вызов ядра: `notify` — уведомление сервера шлюза, `return` — возврат покупателя, другие действия — на усмотрение типа.
     * Секрет заказа в адрес не входит: подлинность проверяет тип (подпись, секрет шлюза).
     *
     * @param   array<string, string>  $extra  дополнительные параметры
     */
    public function callback(string $action, array $extra = []): string
    {
        $query = ['option' => 'com_dharmashop', 'task' => 'payment.callback', 'method' => $this->methodId, 'order' => $this->orderId, 'action' => $action] + $extra;

        return self::absolute('index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private static function absolute(string $url): string
    {
        return Route::link('site', $url, false, Route::TLS_IGNORE, true);
    }
}
