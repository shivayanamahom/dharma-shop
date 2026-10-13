<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Представление сохранённых сумм; цены каталога и корзина не загружаются. */
final class OrderDisplay
{
    public readonly MoneyFormatter $money;
    public readonly string $date;
    public readonly string $total;
    public readonly string $subtotal;
    public readonly string $beforeDiscount;
    public readonly string $productDiscount;
    public readonly bool $hasProductDiscount;

    public function __construct(object $order, Registry $settings)
    {
        $params = clone $settings;
        if ((string) $order->currency !== (string) $params->get('currency_code', 'RUB')) {
            $params->set('currency_symbol', (string) $order->currency);
        }
        $this->money = new MoneyFormatter($params);
        $this->date = HTMLHelper::_('date', $order->created, 'd.m.Y H:i');
        $unknown = false;
        $beforeDiscount = 0.0;
        $productDiscount = 0.0;
        foreach ($order->items as $item) {
            if ($item->price === null || ($this->money->replacesZero() && (float) $item->price === 0.0 && !self::isFree($item))) {
                $unknown = true;
            }

            $itemParams = json_decode((string) ($item->params ?? ''), true) ?: [];
            $price = $item->price === null ? 0.0 : (float) $item->price;
            $basePrice = isset($itemParams['base_price']) && is_numeric($itemParams['base_price'])
                ? (float) $itemParams['base_price']
                : $price;
            $qty = (float) $item->qty;
            $beforeDiscount += $basePrice * $qty;
            $productDiscount += max(0.0, $basePrice - $price) * $qty;
        }
        $this->total = $this->amount($order->total, $unknown);
        $this->subtotal = $this->amount($order->subtotal, $unknown);
        $this->beforeDiscount = $this->amount((string) round($beforeDiscount, 2), $unknown);
        $this->productDiscount = $this->money->format(round($productDiscount, 2));
        $this->hasProductDiscount = round($productDiscount, 2) > 0;
    }

    private function amount(string $value, bool $unknown): string
    {
        $amount = $this->money->format($value);
        return $unknown
            ? ((float) $value > 0 ? $amount . ' + ' : '') . $this->money->zeroText()
            : $amount;
    }

    /** Строка заказа с ценой 0 оформлена как бесплатная (правило товара или категории на момент заказа). */
    public static function isFree(object $item): bool
    {
        return !empty((json_decode((string) ($item->params ?? ''), true) ?: [])['zero_free']);
    }

    public static function quantity(string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', "\u{00A0}"), '0'), ',');
    }
}
