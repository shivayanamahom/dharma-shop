<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Запись способа доставки (строка #__dharmashop_shipping_methods) для плагина типа. */
final class Method
{
    /** @param array<string, mixed> $params настройки типа */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $plugin,
        public readonly string $description,
        public readonly array $params,
        public readonly bool $isDefault,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            (string) $row->title,
            (string) $row->plugin,
            (string) ($row->description ?? ''),
            (new Registry((string) ($row->params ?? '')))->toArray(),
            (int) ($row->is_default ?? 0) === 1,
        );
    }
}
