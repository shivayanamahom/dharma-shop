<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Warehouse;

\defined('_JEXEC') or die;

/** Склад (пункт): адрес, контакты, часы работы, срок готовности самовывоза, координаты. Используется способами доставки и другими частями магазина. */
final class Warehouse
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $address,
        public readonly string $phone,
        public readonly string $email,
        public readonly string $hours,
        public readonly string $info,
        public readonly string $readyTerm,
        public readonly ?float $lat,
        public readonly ?float $lon,
        public readonly string $image,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            (string) $row->title,
            (string) ($row->address ?? ''),
            (string) ($row->phone ?? ''),
            (string) ($row->email ?? ''),
            (string) ($row->hours ?? ''),
            (string) ($row->info ?? ''),
            (string) ($row->ready_term ?? ''),
            $row->lat !== null ? (float) $row->lat : null,
            $row->lon !== null ? (float) $row->lon : null,
            (string) ($row->image ?? ''),
        );
    }

    /** @return array{0: float, 1: float}|null  [широта, долгота] */
    public function coords(): ?array
    {
        return $this->lat !== null && $this->lon !== null ? [$this->lat, $this->lon] : null;
    }
}
