<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Customer;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

/** Вызывается внутри транзакции заказа. Email/телефон не используются для определения владельца. */
final class CustomerService
{
    public function __construct(private readonly DatabaseInterface $db) {}
    public function forOrder(int $userId, ?int $guestCustomerId, array $profile, string $now): int
    {
        $data = [(string) $profile['name'],(string) $profile['email'],(string) $profile['phone'],preg_replace('/\D/','',(string) $profile['phone']),$now];
        $id = 0;
        if ($userId > 0) {
            // UNIQUE user_id защищает и параллельные первые заказы из разных корзин.
            Sql::exec($this->db, 'INSERT INTO #__dharmashop_customers (user_id,name,email,phone,phone_norm,created) VALUES (:u,:n,:e,:p,:pn,:c) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
                [':u'=>[$userId,ParameterType::INTEGER],':n'=>[$data[0]],':e'=>[$data[1]],':p'=>[$data[2]],':pn'=>[$data[3]],':c'=>[$now]]);
            $id = (int) $this->db->insertid();
        } elseif ($guestCustomerId) {
            $query = $this->db->createQuery()->select('id')->from($this->db->quoteName('#__dharmashop_customers'))
                ->where('id=:id')->where('user_id IS NULL')->bind(':id',$guestCustomerId,ParameterType::INTEGER);
            $id = (int) $this->db->setQuery($query)->loadResult();
        }
        if ($id > 0) {
            Sql::exec($this->db, 'UPDATE #__dharmashop_customers SET name=:n,email=:e,phone=:p,phone_norm=:pn,modified=:m WHERE id=:id',
                [':n'=>[$data[0]],':e'=>[$data[1]],':p'=>[$data[2]],':pn'=>[$data[3]],':m'=>[$now],':id'=>[$id,ParameterType::INTEGER]]);
            return $id;
        }
        $object = (object) ['user_id'=>null,'name'=>$data[0],'email'=>$data[1],'phone'=>$data[2],'phone_norm'=>$data[3],'created'=>$now];
        $this->db->insertObject('#__dharmashop_customers',$object);
        return (int) $this->db->insertid();
    }
}
