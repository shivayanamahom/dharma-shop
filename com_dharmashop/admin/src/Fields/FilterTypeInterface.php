<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;
\defined('_JEXEC') or die;

/** Optional provider contract. Values stay in the component's indexed typed storage; providers never inject SQL. */
interface FilterTypeInterface extends TypeInterface
{
    /** option|number|range, or null when this type cannot be filtered. Must agree with store(). */
    public function filterKind(): ?string;
}
