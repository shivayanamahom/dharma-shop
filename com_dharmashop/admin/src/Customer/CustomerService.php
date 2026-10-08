<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Customer;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

/** В транзакции заказа. Гостевая пара — связь CRM, доступ к заказам задаётся отдельно. */
final class CustomerService
{
    public function __construct(private readonly DatabaseInterface $db) {}
    public function forOrder(int $userId, array $profile, string $now): int
    {
        $name = trim((string) $profile['name']);
        $email = trim((string) $profile['email']);
        $phone = trim((string) $profile['phone']);
        $normal = preg_replace('/\D/', '', $phone);
        if ($userId > 0) {
            // Аккаунты никогда не определяются по введённым гостевым контактам.
            Sql::exec($this->db, 'INSERT INTO #__dharmashop_customers (user_id,name,email,phone,phone_norm,created) VALUES (:u,:n,:e,:p,:pn,:c) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
                [':u'=>[$userId,ParameterType::INTEGER],':n'=>[$name],':e'=>[$email],':p'=>[$phone],':pn'=>[$normal],':c'=>[$now]]);
            $id = (int) $this->db->insertid();
            Sql::exec($this->db, "UPDATE #__dharmashop_customers SET name=IF(name='',:n,name),email=:e,phone=:p,phone_norm=:pn,modified=:m WHERE id=:id",
                [':n'=>[$name],':e'=>[$email],':p'=>[$phone],':pn'=>[$normal],':m'=>[$now],':id'=>[$id,ParameterType::INTEGER]]);
            return $id;
        }
        $key = ContactKey::guest($email, $phone);
        if ($key !== null) {
            // UNIQUE contact_key исключает дубли при одновременных заказах из разных корзин.
            Sql::exec($this->db, 'INSERT INTO #__dharmashop_customers (user_id,name,email,phone,phone_norm,contact_key,created) VALUES (NULL,:n,:e,:p,:pn,:key,:c) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
                [':n'=>[$name],':e'=>[$email],':p'=>[$phone],':pn'=>[$normal],':key'=>[$key],':c'=>[$now]]);
            $id = (int) $this->db->insertid();
            if ($name !== '') Sql::exec($this->db, "UPDATE #__dharmashop_customers SET name=:n,modified=:m WHERE id=:id AND name=''",
                [':n'=>[$name],':m'=>[$now],':id'=>[$id,ParameterType::INTEGER]]);
            return $id;
        }
        // Недостаточно контактов для сопоставления: новая запись, даже в прежней корзине.
        $object = (object) ['user_id'=>null,'name'=>$name,'email'=>$email,'phone'=>$phone,'phone_norm'=>$normal,'contact_key'=>null,'created'=>$now];
        $this->db->insertObject('#__dharmashop_customers',$object);
        return (int) $this->db->insertid();
    }
}
