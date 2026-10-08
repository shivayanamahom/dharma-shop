<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Notification;
\defined('_JEXEC') or die;

/** Снимок конкретного события истории, общий для всех каналов уведомлений. */
final readonly class OrderNotification
{
    public function __construct(
        public int $historyId,
        public string $reason,
        public array $order,
        public array $items,
        public array $adjustments,
        public array $contacts,
    ) {}
}
