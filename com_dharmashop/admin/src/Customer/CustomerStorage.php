<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Customer;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

/** Только установка/обновление: аддитивная миграция без объединения исторических записей. */
final class CustomerStorage
{
    public static function upgrade(DatabaseInterface $db): void
    {
        $table = $db->replacePrefix('#__dharmashop_customers');
        if (!in_array($table, $db->getTableList(), true)) return;
        $columns = $db->getTableColumns($table, false);
        if (!isset($columns['contact_key'])) {
            $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD COLUMN contact_key char(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER phone_norm')->execute();
        }
        $indexes = $db->setQuery('SHOW INDEX FROM ' . $db->quoteName($table))->loadObjectList();
        if (!array_filter($indexes, static fn(object $index): bool => $index->Key_name === 'uq_guest_contact')) {
            $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD UNIQUE KEY uq_guest_contact (contact_key)')->execute();
        }
        $cursor = 0;
        do {
            $rows = $db->setQuery(Sql::query($db, "SELECT id,email,phone_norm FROM #__dharmashop_customers WHERE id>:id AND user_id IS NULL AND contact_key IS NULL AND email<>'' AND phone_norm<>'' ORDER BY id LIMIT 200", [':id'=>[$cursor,ParameterType::INTEGER]]))->loadAssocList();
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                $key = ContactKey::guest($row['email'], $row['phone_norm']);
                if ($key === null) continue;
                // Единственное игнорируемое ограничение здесь — уже занятая полная пара контактов.
                // Исторический дубль остаётся самостоятельной записью, его заказы не переносим.
                Sql::exec($db, 'UPDATE IGNORE #__dharmashop_customers SET contact_key=:key WHERE id=:id AND contact_key IS NULL AND user_id IS NULL',
                    [':key'=>[$key], ':id'=>[$cursor,ParameterType::INTEGER]]);
            }
        } while (count($rows) === 200);
    }
}
