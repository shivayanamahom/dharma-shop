<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Seo;

\defined('_JEXEC') or die;

/** Семантика списка, подготовленная видом из доступных и показанных записей. */
final readonly class CollectionMetadata
{
    /** @param list<array{name: string, url: string}> $items Элементы в порядке вывода карточек. */
    public function __construct(
        public string $name,
        public array $items,
        public string $itemType = 'CollectionPage',
    ) {
    }
}
