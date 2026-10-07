<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;
\defined('_JEXEC') or die;

/** Providers own native input XML and value conversion; the component owns persistence. */
interface ValueTypeInterface extends TypeInterface
{
    public function input(object $field, array $options): \SimpleXMLElement;
    public function defaultValue(object $field, array $options): mixed;
    public function decode(object $field, array $rows, ?string $text): mixed;
    /** @return array{rows: array, text: ?string} Empty rows and null text mean clear. */
    public function encode(object $field, array $options, mixed $value): array;
}
