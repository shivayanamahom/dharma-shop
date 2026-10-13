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
        /** Доступность выбранного варианта: учёт отключён, остаток > 0 или разрешён заказ без остатка (как на витрине). */
        public bool $inStock,
        /** Все изображения товара (абсолютные адреса), основное первым. @var list<string> */
        public array $images = [],
        /** Характеристики: [название, значение] (значение с единицей измерения). @var list<array{0: string, 1: string}> */
        public array $properties = [],
        /** Цена до скидки (зачёркнутая) и дата окончания скидки `YYYY-MM-DD`; null — скидки нет. */
        public ?string $listPrice = null,
        public ?string $priceValidUntil = null,
        public string $category = '',
        /** Группа вариантов (`ProductGroup`): название, идентификатор, оси и варианты; null — товар без группы. @var array<string, mixed>|null */
        public ?array $group = null,
    ) {
    }
}
