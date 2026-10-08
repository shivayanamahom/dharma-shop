<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Notification;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
\defined('_JEXEC') or die;

/** Dispatch только после commit. Отправка и журнал канала не входят в транзакцию заказа. */
final class NotificationService
{
    public function __construct(private readonly DatabaseInterface $db) {}

    public function notify(int $historyId, bool $retry = false): void
    {
        try {
            $query = $this->db->createQuery()->select([
                'h.id AS history_id', 'h.created AS event_date', 'h.status_id', 'o.id', 'o.number', 'o.user_id', 'o.customer_id', 'o.currency',
                'o.created', 'o.total', 'o.subtotal', 'o.contact_name', 'o.contact_email', 'o.contacts', 'o.shipping_title', 'o.payment_title',
                's.title AS status_title', 's.customer_message AS status_message',
            ])->from($this->db->quoteName('#__dharmashop_order_history', 'h'))
                ->join('INNER', $this->db->quoteName('#__dharmashop_orders', 'o') . ' ON o.id=h.order_id')
                ->join('LEFT', $this->db->quoteName('#__dharmashop_statuses', 's') . ' ON s.id=h.status_id')
                ->where('h.id=:id')->bind(':id', $historyId, ParameterType::INTEGER);
            $order = $this->db->setQuery($query)->loadAssoc();
            if (!$order) return;
            $orderId = (int) $order['id'];
            $first = (int) $this->db->setQuery(Sql::query($this->db, 'SELECT MIN(id) FROM #__dharmashop_order_history WHERE order_id=:id', [':id'=>[$orderId,ParameterType::INTEGER]]))->loadResult();
            $contacts = json_decode((string) $order['contacts'], true) ?: [];
            unset($order['contacts']);
            $items = $this->db->setQuery(Sql::query($this->db, 'SELECT title,sku,unit,qty,price,total,params FROM #__dharmashop_order_items WHERE order_id=:id ORDER BY ordering,id', [':id'=>[$orderId,ParameterType::INTEGER]]))->loadAssocList();
            $adjustments = $this->db->setQuery(Sql::query($this->db, 'SELECT title,amount FROM #__dharmashop_order_adjustments WHERE order_id=:id ORDER BY ordering,id', [':id'=>[$orderId,ParameterType::INTEGER]]))->loadAssocList();
            $notification = new OrderNotification($historyId, $first === $historyId ? 'created' : 'status', $order, $items, $adjustments, $contacts);
            PluginHelper::importPlugin('dharmashopnotify');
            Factory::getApplication()->getDispatcher()->dispatch('onDharmaShopOrderNotify', new Event('onDharmaShopOrderNotify', [
                'notification'=>$notification, 'settings'=>clone ComponentHelper::getParams('com_dharmashop'), 'retry'=>$retry,
            ]));
        } catch (\Throwable $error) {
            // Без контактов, текста SMTP-ответа и реквизитов подключения.
            Log::add('Notification event ' . $historyId . ': ' . get_class($error), Log::ERROR, 'com_dharmashop');
        }
    }

    /** Уникальный claim исключает повтор/параллельную отправку получателю. state=0 не повторяется автоматически. */
    public function claim(OrderNotification $message, string $channel, string $audience, string $recipient, bool $retry): ?int
    {
        $key = bin2hex(random_bytes(16));
        $hash = hash('sha256', $recipient);
        $now = Factory::getDate()->toSql();
        $values = [':o'=>[(int) $message->order['id'],ParameterType::INTEGER], ':h'=>[$message->historyId,ParameterType::INTEGER],
            ':c'=>[$channel], ':a'=>[$audience], ':r'=>[$recipient], ':hash'=>[$hash], ':key'=>[$key], ':now'=>[$now]];
        if ($retry) {
            Sql::exec($this->db, 'UPDATE #__dharmashop_notifications SET state=0,claim_key=:key,attempts=attempts+1,error_code=\'\' WHERE history_id=:h AND channel=:c AND audience=:a AND recipient_hash=:hash AND state=2',
                [':key'=>[$key], ':h'=>[$message->historyId,ParameterType::INTEGER], ':c'=>[$channel], ':a'=>[$audience], ':hash'=>[$hash]]);
        } else {
            Sql::exec($this->db, 'INSERT INTO #__dharmashop_notifications (order_id,history_id,channel,audience,recipient,recipient_hash,claim_key,created) VALUES (:o,:h,:c,:a,:r,:hash,:key,:now) ON DUPLICATE KEY UPDATE id=id', $values);
        }
        $id = $this->db->setQuery(Sql::query($this->db, 'SELECT id FROM #__dharmashop_notifications WHERE claim_key=:key AND history_id=:h AND state=0', [':key'=>[$key], ':h'=>[$message->historyId,ParameterType::INTEGER]]))->loadResult();
        return $id ? (int) $id : null;
    }

    public function finish(int $id, int $historyId, string $audience, bool $sent, string $error = ''): void
    {
        Sql::exec($this->db, 'UPDATE #__dharmashop_notifications SET state=:state,sent=:sent,error_code=:error WHERE id=:id',
            [':state'=>[$sent ? 1 : 2,ParameterType::INTEGER], ':sent'=>[$sent ? Factory::getDate()->toSql() : null,$sent ? ParameterType::STRING : ParameterType::NULL], ':error'=>[substr($error,0,100)], ':id'=>[$id,ParameterType::INTEGER]]);
        if ($sent && $audience === 'customer') Sql::exec($this->db, 'UPDATE #__dharmashop_order_history SET notified=1 WHERE id=:id', [':id'=>[$historyId,ParameterType::INTEGER]]);
    }

    public function lifecycle(string $event, array $arguments): void
    {
        try {
            PluginHelper::importPlugin('dharmashop');
            Factory::getApplication()->getDispatcher()->dispatch($event, new Event($event, $arguments));
        } catch (\Throwable $error) {
            Log::add($event . ': ' . get_class($error), Log::ERROR, 'com_dharmashop');
        }
    }
}
