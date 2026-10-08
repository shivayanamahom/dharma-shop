<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Order;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Общие определения и серверная проверка чекаута; названия/required берём только из params. */
final class CheckoutFields
{
    public static function definitions(mixed $value): array
    {
        $value = $value instanceof Registry ? $value->toArray() : (is_object($value) ? get_object_vars($value) : $value);
        if (!is_array($value)) return [];
        $rows = []; $names = [];
        foreach (array_slice($value, 0, 50) as $row) {
            $row = $row instanceof Registry ? $row->toArray() : (is_object($row) ? get_object_vars($row) : $row);
            if (!is_array($row) || (int) ($row['enabled'] ?? 1) !== 1) continue;
            $name = (string) ($row['name'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) || isset($names[$name])) continue;
            $names[$name] = true; $rows[] = $row;
        }
        return $rows;
    }
    public static function contacts(Registry $settings): array
    {
        return array_values(array_filter(self::definitions($settings->get('checkout_fields', [])),
            static fn (array $row): bool => in_array($row['type'] ?? '', ['text','tel','email','textarea'], true)));
    }
    /** @return array{values:array,errors:array,snapshot:array,profile:array} */
    public static function validate(Registry $settings, array $input, array $consents): array
    {
        $values = []; $errors = []; $fields = []; $accepted = [];
        $profile = ['name'=>'','email'=>'','phone'=>''];
        foreach (self::contacts($settings) as $field) {
            $name = $field['name']; $raw = $input[$name] ?? '';
            $validScalar = is_string($raw) || is_numeric($raw);
            $value = $validScalar ? trim(strip_tags((string) $raw)) : '';
            $type = $field['type'];
            $limit = match ($type) { 'textarea'=>2000, 'email'=>254, 'tel'=>64, default=>150 };
            if (!$validScalar || mb_strlen($value) > $limit || ($value === '' && (int) ($field['required'] ?? 0) === 1)) {
                $errors[$name] = Text::sprintf('COM_DHARMASHOP_CHECKOUT_FIELD_INVALID', $field['label']);
            } elseif ($value !== '' && $type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = Text::sprintf('COM_DHARMASHOP_CHECKOUT_FIELD_INVALID', $field['label']);
            } elseif ($value !== '' && $type === 'tel') {
                $mask = trim((string) ($field['mask'] ?? '')) ?: '+7 (###) ###-##-##';
                $digits = preg_replace('/\D/', '', $value);
                $capacity = substr_count($mask, '#');
                $prefix = preg_replace('/\D/', '', strstr($mask, '#', true) ?: '');
                if ($prefix !== '' && strlen($digits) === $capacity + strlen($prefix) && str_starts_with($digits, $prefix)) $digits = substr($digits, strlen($prefix));
                if ($capacity < 1 || strlen($digits) !== $capacity || $capacity + strlen($prefix) > 20 || preg_match('/[^0-9+()\s.\-]/u', $value)) {
                    $errors[$name] = Text::sprintf('COM_DHARMASHOP_CHECKOUT_FIELD_INVALID', $field['label']);
                } else {
                    $offset = 0; $value = preg_replace_callback('/#/', static function () use (&$offset, $digits): string { return $digits[$offset++]; }, $mask);
                }
            }
            $values[$name] = $value;
            $fields[] = ['name'=>$name,'label'=>(string) $field['label'],'type'=>$type,'value'=>$value];
            if (!isset($errors[$name])) {
                if ($type === 'email' && $profile['email'] === '') $profile['email'] = $value;
                if ($type === 'tel' && $profile['phone'] === '') $profile['phone'] = mb_strlen($value) <= 50 ? $value : preg_replace('/\D/', '', $value);
                if ($type === 'text' && $profile['name'] === '' && (in_array($name, ['name','recipient_name','full_name','fio','imya'], true)
                    || in_array(mb_strtolower(trim((string) $field['label'])), ['имя','фио','фамилия имя отчество','name','full name'], true))) $profile['name'] = $value;
            }
        }
        foreach (self::definitions($settings->get('checkout_consents', [])) as $row) {
            $checked = isset($consents[$row['name']]) && is_scalar($consents[$row['name']]) && (string) $consents[$row['name']] === '1';
            if (!$checked && (int) ($row['required'] ?? 1) === 1) $errors['consent_' . $row['name']] = Text::_('COM_DHARMASHOP_CHECKOUT_CONSENT_REQUIRED');
            $accepted[] = ['name'=>$row['name'],'text'=>(string) ($row['text'] ?? ''),'url'=>(string) ($row['url'] ?? ''),
                'link_label'=>(string) ($row['link_label'] ?? ''),'required'=>(int) ($row['required'] ?? 1),'accepted'=>$checked];
        }
        return ['values'=>$values,'errors'=>$errors,'snapshot'=>['version'=>1,'fields'=>$fields,'consents'=>$accepted],'profile'=>$profile];
    }
}
