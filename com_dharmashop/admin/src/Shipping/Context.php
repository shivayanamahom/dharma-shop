<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

\defined('_JEXEC') or die;

/** Серверные данные заказа для расчёта доставки: суммы без доставки, состав, контакты. */
final class Context
{
    /**
     * @param   float                  $total     к оплате без доставки (после скидок и сборов)
     * @param   float                  $subtotal  сумма позиций
     * @param   float                  $qty       всего штук
     * @param   int                    $count     число позиций
     * @param   array<int, object>     $lines     позиции корзины без ошибок (результат CartService::lines)
     * @param   array<string, mixed>   $contacts  введённые контакты покупателя (name, email, phone…), если известны
     */
    public function __construct(
        public readonly float $total,
        public readonly float $subtotal,
        public readonly float $qty,
        public readonly int $count,
        public readonly string $currency,
        public readonly array $lines = [],
        public readonly array $contacts = [],
    ) {
    }
}
