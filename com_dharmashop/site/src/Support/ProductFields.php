<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** Compatibility entry point for product list/page callers. */
final class ProductFields
{
    public static function attach(DatabaseInterface $db, array $items, string $context): void
    {
        EntityFields::attach($db, $items, $context, 'product');
    }
}
