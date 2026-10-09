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

\defined('_JEXEC') or die;

/** Заказ глазами типа оплаты: только то, что нужно для платежа. `key` — секрет заказа (orders.secret), им можно подписывать запросы. */
final class OrderPayment
{
    /** @param array<string, mixed> $data снимок данных оплаты (payment_data.data) */
    public function __construct(
        public readonly int $id,
        public readonly string $number,
        public readonly string $key,
        public readonly float $total,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly array $data = [],
    ) {
    }
}
