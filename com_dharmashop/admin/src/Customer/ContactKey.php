<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Customer;
\defined('_JEXEC') or die;

/** Индекс CRM, не подтверждение личности и не ключ доступа к заказам. */
final class ContactKey
{
    public static function guest(string $email, string $phone): ?string
    {
        $email = mb_strtolower(trim($email));
        $phone = preg_replace('/\D/', '', $phone);
        return $email !== '' && $phone !== '' ? hash('sha256', $email . "\0" . $phone) : null;
    }
}
