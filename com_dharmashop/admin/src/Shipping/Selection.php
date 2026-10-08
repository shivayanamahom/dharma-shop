<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

\defined('_JEXEC') or die;

/** Проверенный выбор покупателя: способ, расчёт и данные для снимка заказа. */
final class Selection
{
    /**
     * @param   array<string, mixed>                              $data  введённые покупателем данные (после проверки типом)
     * @param   array<int, array{label: string, value: string}>  $rows  сведения для показа: постоянные (describe) и введённые покупателем
     */
    public function __construct(
        public readonly Method $method,
        public readonly Quote $quote,
        public readonly array $data,
        public readonly array $rows = [],
    ) {
    }

    /**
     * JSON снимка для `orders.shipping_data` (C80). Показ в админке, на странице заказа и в письмах идёт только из снимка
     * (`title`, `price`, `free`, `term`, `rows`), поэтому не зависит от того, установлен ли плагин типа позже.
     */
    public function snapshot(): array
    {
        return [
            'type' => $this->method->plugin,
            'title' => $this->method->title,
            'price' => $this->quote->price,
            'free' => $this->quote->free,
            'term' => $this->quote->term,
            'rows' => $this->rows,
            'data' => $this->data,
        ];
    }
}
