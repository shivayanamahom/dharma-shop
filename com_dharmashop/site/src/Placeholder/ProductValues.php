<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Placeholder;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Значения, доступные карточке из уже загруженной строки товара; без дополнительных запросов. */
final class ProductValues
{
    /** @return array<string, string> */
    public static function build(object $item, MoneyFormatter $money): array
    {
        $min = $item->price_min ?? null;
        $max = $item->price_max ?? null;

        return [
            'product.title' => (string) $item->title,
            'product.sku' => (string) ($item->sku ?? ''),
            'product.category' => (string) ($item->category_title ?? ''),
            'product.brand' => (string) ($item->brand_title ?? ''),
            'product.price' => $money->range($min, $max, !empty($item->price_free)),
            'product.price_old' => $money->format($item->base_price ?? null),
            'product.discount' => (float) ($item->discount_percent ?? 0) > 0
                ? '−' . rtrim(rtrim(number_format((float) $item->discount_percent, 2, ',', ''), '0'), ',') . '%' : '',
            // Алиасы для ранее сохранённых шаблонов; в справочнике только product.price.
            'product.price_min' => $money->price($min !== null ? (string) $min : null, !empty($item->price_free)),
            'product.price_max' => $money->price($max !== null ? (string) $max : null, !empty($item->price_free)),
            'product.unit' => (string) ($item->unit ?? ''),
            'product.stock' => !empty($item->in_stock) ? Text::_('COM_DHARMASHOP_PRODUCT_IN_STOCK') : Text::_('COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK'),
            'product.introtext' => self::introtext((string) ($item->introtext ?? '')),
        ];
    }

    /** Текст подстановки и предпросмотра: без HTML/сущностей, не более 200 символов. */
    public static function introtext(string $html): string
    {
        $html = (string) preg_replace('~</?(?:p|div|li|h[1-6]|br)\b[^>]*>~i', ' ', $html);
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

        return mb_strlen($plain) > 200 ? rtrim(mb_substr($plain, 0, 199)) . '…' : $plain;
    }
}
