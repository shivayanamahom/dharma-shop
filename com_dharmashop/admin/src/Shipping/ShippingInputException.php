<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

\defined('_JEXEC') or die;

/** Ошибка выбора доставки (способ недоступен или поля не заполнены); сообщение показывается покупателю. */
final class ShippingInputException extends \DomainException
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
