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

/** Итог обратного вызова шлюза: статус платежа и что ответить (переход покупателя или тело ответа серверу шлюза). */
final class Outcome
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    /** Запрос не относится к статусу (например, показ страницы): ядро ничего не меняет. */
    public const IGNORED = 'ignored';

    private function __construct(
        public readonly string $status,
        public readonly string $ref = '',
        public readonly string $message = '',
        public readonly string $redirect = '',
        public readonly string $body = '',
        public readonly int $httpCode = 200,
        public readonly float $amount = 0.0,
    ) {
    }

    /** @param string $redirect адрес для перехода покупателя (если вызов — возврат со шлюза); $body — ответ серверу шлюза (если webhook) */
    public static function paid(string $ref = '', float $amount = 0.0, string $redirect = '', string $body = 'OK'): self
    {
        return new self(self::PAID, $ref, '', $redirect, $body, 200, $amount);
    }

    public static function pending(string $ref = '', string $redirect = '', string $body = 'OK'): self
    {
        return new self(self::PENDING, $ref, '', $redirect, $body);
    }

    public static function failed(string $message = '', string $ref = '', string $redirect = '', string $body = 'OK'): self
    {
        return new self(self::FAILED, $ref, $message, $redirect, $body);
    }

    public static function cancelled(string $message = '', string $ref = '', string $redirect = '', string $body = 'OK'): self
    {
        return new self(self::CANCELLED, $ref, $message, $redirect, $body);
    }

    /** Подлинность не подтверждена или действие неизвестно: ничего не меняем, серверу шлюза отвечаем кодом ошибки. */
    public static function ignored(string $body = 'Bad request', int $httpCode = 400, string $redirect = ''): self
    {
        return new self(self::IGNORED, '', '', $redirect, $body, $httpCode);
    }
}
