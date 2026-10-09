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

\defined('_JEXEC') or die;

/** Что делать с покупателем после создания заказа: ничего (оплата вне сайта), перейти на шлюз или на страницу оплаты сайта. */
final class Initiation
{
    public const NONE = 'none';
    public const REDIRECT = 'redirect';
    public const PAGE = 'page';

    private function __construct(
        public readonly string $kind,
        public readonly string $url = '',
        /** @var array<string, mixed> данные платежа для снимка (идентификатор платежа шлюза и т. п.) */
        public readonly array $data = [],
    ) {
    }

    /** Оплата вне сайта (наличными, по счёту): страница оплаты не нужна, сведения — в заказе и письме. */
    public static function none(): self
    {
        return new self(self::NONE);
    }

    /** Перейти на страницу внешнего шлюза. @param array<string, mixed> $data */
    public static function redirect(string $url, array $data = []): self
    {
        return new self(self::REDIRECT, $url, $data);
    }

    /** Открыть страницу оплаты на сайте (`view=payment`, макет типа `renderPayment`). @param array<string, mixed> $data */
    public static function page(array $data = []): self
    {
        return new self(self::PAGE, '', $data);
    }
}
