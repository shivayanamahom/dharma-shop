<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Seo;

\defined('_JEXEC') or die;

/** Снимок доступного товара для JSON-LD. Цена — число, без форматирования и текста «по запросу». */
final readonly class ProductMetadata
{
    public function __construct(
        public string $name,
        public string $sku,
        public string $brand,
        public string $description,
        public string $image,
        public ?string $price,
        public string $currency,
        /** Фактический остаток выбранного варианта; при отключённом учёте — true. */
        public bool $inStock,
    ) {
    }
}
