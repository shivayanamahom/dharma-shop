<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Payment;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Способ оплаты: строка `payment_methods` с разобранными настройками типа. */
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
        public readonly string $image = '',
        public readonly int $paidStatusId = 0,
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
            (string) ($row->image ?? ''),
            (int) ($row->paid_status_id ?? 0),
        );
    }
}
