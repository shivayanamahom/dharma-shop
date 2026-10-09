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

/** Серверные сведения о корзине для способа оплаты (C83). Значения из браузера не доверяются. */
final class Context
{
    /**
     * @param   float                  $total     к оплате с доставкой (итог заказа)
     * @param   float                  $subtotal  сумма позиций
     * @param   float                  $qty       всего штук
     * @param   int                    $count     число позиций
     * @param   array<int, object>     $lines     позиции корзины без ошибок (результат CartService::lines)
     * @param   array<string, mixed>   $contacts  введённые контакты покупателя, если известны
     * @param   int                    $shippingId  выбранный способ доставки (0 — не выбран)
     */
    public function __construct(
        public readonly float $total,
        public readonly float $subtotal,
        public readonly float $qty,
        public readonly int $count,
        public readonly string $currency,
        public readonly array $lines = [],
        public readonly array $contacts = [],
        public readonly int $shippingId = 0,
    ) {
    }
}
