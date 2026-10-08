<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Предзаполнение формы оформления данными вошедшего пользователя. Источники по убыванию приоритета: контакты его последнего заказа
 * (снимок хранит значения всех полей конструктора, в том числе произвольных — организация и т. п.), запись покупателя, связанная с его
 * учётной записью (телефон), учётная запись Joomla (имя, e-mail) и поле телефона профиля пользователя. Подставляются только поля,
 * которые есть в текущем конструкторе формы (по техническому имени и типу). Читаются только данные самого пользователя.
 */
final class CheckoutPrefill
{
    /**
     * @param   array<int, array<string, mixed>>  $contacts  поля формы (`CheckoutFields::contacts`)
     *
     * @return  array<string, string>  техническое имя поля => значение
     */
    public static function forUser(DatabaseInterface $db, User $user, array $contacts): array
    {
        $userId = (int) $user->id;

        if ($userId < 1 || !$contacts) {
            return [];
        }

        $values = [];
        $order = $db->setQuery(
            $db->createQuery()->select($db->quoteName('contacts'))->from($db->quoteName('#__dharmashop_orders'))
                ->where($db->quoteName('user_id') . ' = :user')->order($db->quoteName('id') . ' DESC')->bind(':user', $userId, ParameterType::INTEGER),
            0,
            1
        )->loadResult();
        $snapshot = [];

        foreach ((array) (json_decode((string) $order, true)['fields'] ?? []) as $row) {
            if (is_array($row) && isset($row['name'], $row['type'], $row['value'])) {
                $snapshot[(string) $row['name'] . '|' . (string) $row['type']] = trim((string) $row['value']);
            }
        }

        $customer = $db->setQuery(
            $db->createQuery()->select($db->quoteName(['name', 'email', 'phone']))->from($db->quoteName('#__dharmashop_customers'))
                ->where($db->quoteName('user_id') . ' = :user')->bind(':user', $userId, ParameterType::INTEGER),
            0,
            1
        )->loadAssoc() ?: [];
        $phone = trim((string) ($customer['phone'] ?? ''));

        if ($phone === '') {
            // Телефон из профиля Joomla (плагин «Профиль пользователя»), если он есть.
            $phone = trim((string) $db->setQuery(
                $db->createQuery()->select($db->quoteName('profile_value'))->from($db->quoteName('#__user_profiles'))
                    ->where($db->quoteName('user_id') . ' = :user')->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.phone'))
                    ->bind(':user', $userId, ParameterType::INTEGER),
                0,
                1
            )->loadResult());
            $phone = trim($phone, '"');
        }

        $roleValues = ['name' => (string) $user->name, 'email' => (string) $user->email, 'phone' => $phone];

        foreach ($contacts as $field) {
            $name = (string) $field['name'];
            $type = (string) $field['type'];
            $value = $snapshot[$name . '|' . $type] ?? '';

            if ($value === '') {
                $role = self::role($field);
                $value = $role !== '' ? $roleValues[$role] : '';
            }

            if ($value !== '') {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /** Смысл поля: name | email | phone | пусто. Правило то же, что при записи покупателя (`CheckoutFields::validate`). */
    private static function role(array $field): string
    {
        $type = (string) $field['type'];

        if ($type === 'email') {
            return 'email';
        }

        if ($type === 'tel') {
            return 'phone';
        }

        $label = mb_strtolower(trim((string) ($field['label'] ?? '')));

        return $type === 'text' && (in_array((string) $field['name'], ['name', 'recipient_name', 'full_name', 'fio', 'imya'], true)
            || in_array($label, ['имя', 'фио', 'фамилия имя отчество', 'name', 'full name'], true)) ? 'name' : '';
    }
}
