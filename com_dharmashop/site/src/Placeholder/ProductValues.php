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
        $intro = trim(strip_tags((string) ($item->introtext ?? '')));
        $min = $item->price_min ?? null;
        $max = $item->price_max ?? null;

        return [
            'product.title' => (string) $item->title,
            'product.price_min' => $min !== null ? $money->format((string) $min) : '',
            'product.price_max' => $max !== null ? $money->format((string) $max) : '',
            'product.unit' => (string) ($item->unit ?? ''),
            'product.stock' => !empty($item->in_stock) ? Text::_('COM_DHARMASHOP_PRODUCT_IN_STOCK') : Text::_('COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK'),
            'product.introtext' => mb_substr($intro, 0, 200),
        ];
    }
}
