<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Promotion;

\defined('_JEXEC') or die;

/** Неизменяемые серверные данные для расчёта одного применения акции. */
final class Context
{
    /** @param object[] $lines */
    public function __construct(
        public readonly object $cart,
        public readonly array $lines,
        public readonly float $subtotal,
    ) {}
}
