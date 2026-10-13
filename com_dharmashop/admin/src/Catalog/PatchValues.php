<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Catalog;
\defined('_JEXEC') or die;

/** Stable comparison across JSON snapshots and database scalar types. */
final class PatchValues
{
    public static function hash(mixed $value): string
    {
        $normalize = static function (mixed $v) use (&$normalize): mixed {
            if (is_object($v)) { $v = (array) $v; }
            if (is_array($v)) { if (!array_is_list($v)) { ksort($v); } return array_map($normalize, $v); }
            return $v === null ? null : (string) $v;
        };
        return hash('sha256', json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
