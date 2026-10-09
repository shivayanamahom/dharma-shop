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

/** Ошибка выбора оплаты на оформлении: сообщение и ошибки полей типа. */
final class PaymentInputException extends \DomainException
{
    /** @param array<string, string> $errors [поле => языковой ключ] */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
