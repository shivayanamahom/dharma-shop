<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

\defined('_JEXEC') or die;

/** Результат расчёта доставки: цена (0 — бесплатно), срок и примечание для покупателя. */
final class Quote
{
    public function __construct(
        public readonly float $price,
        public readonly string $term = '',
        public readonly string $note = '',
        public readonly bool $free = false,
    ) {
    }
}
