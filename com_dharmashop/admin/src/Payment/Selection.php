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

/** Выбор покупателя: способ оплаты и введённые им данные (после проверки типом). */
final class Selection
{
    /**
     * @param   array<string, mixed>                              $data  данные покупателя
     * @param   array<int, array{label: string, value: string}>  $rows  сведения для показа: постоянные (describe) и введённые покупателем
     */
    public function __construct(
        public readonly Method $method,
        public readonly array $data,
        public readonly array $rows = [],
    ) {
    }

    /** JSON снимка для `orders.payment_data`: заказ, страница заказа и письма показывают оплату только из него. */
    public function snapshot(): array
    {
        return [
            'type' => $this->method->plugin,
            'title' => $this->method->title,
            'rows' => $this->rows,
            'data' => $this->data,
        ];
    }
}
