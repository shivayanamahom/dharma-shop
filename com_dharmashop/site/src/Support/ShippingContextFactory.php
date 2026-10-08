<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Контекст расчёта доставки по текущей корзине (серверные суммы и позиции без ошибок); null — корзины или годных позиций нет. */
final class ShippingContextFactory
{
    /** @param array<string, mixed> $contacts введённые контакты покупателя, если известны */
    public static function forCart(CartSession $session, Registry $settings, array $contacts = []): ?Context
    {
        $cart = $session->current();

        if ($cart === null) {
            return null;
        }

        $service = $session->service();
        $lines = $service->lines($cart);
        $totals = $service->totals($cart, $lines);
        $valid = array_values(array_filter($lines, static fn (object $line): bool => $line->error === ''));

        if (!$valid) {
            return null;
        }

        return new Context((float) $totals['total'], (float) $totals['subtotal'], (float) $totals['qty'], (int) $totals['count'], (string) $settings->get('currency_code', 'RUB'), $valid, $contacts);
    }
}
