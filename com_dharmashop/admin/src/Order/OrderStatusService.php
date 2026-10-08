<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Order;

use Dharma\Component\DharmaShop\Administrator\Notification\NotificationService;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Dharma\Component\DharmaShop\Administrator\Stock\StockService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

final class OrderStatusService
{
    public function __construct(private readonly DatabaseInterface $db, private readonly NotificationService $notifications) {}

    public function change(int $orderId, int $statusId, int $expected, int $actor): bool
    {
        $this->db->transactionStart();
        try {
            $order = $this->db->setQuery(Sql::query($this->db, 'SELECT id,status_id FROM #__dharmashop_orders WHERE id=:id FOR UPDATE', [':id'=>[$orderId,ParameterType::INTEGER]]))->loadObject();
            if (!$order || (int) $order->status_id !== $expected) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_STATUS_STALE'));
            if ($expected === $statusId) { $this->db->transactionCommit(); return false; }
            $status = $this->db->setQuery(Sql::query($this->db, 'SELECT id,state,stock_effect FROM #__dharmashop_statuses WHERE id=:id', [':id'=>[$statusId,ParameterType::INTEGER]]))->loadObject();
            $oldEffect = (int) $this->db->setQuery(Sql::query($this->db, 'SELECT stock_effect FROM #__dharmashop_statuses WHERE id=:id', [':id'=>[$expected,ParameterType::INTEGER]]))->loadResult();
            if (!$status || (int) $status->state !== 1 || !in_array((int) $status->stock_effect, [0, 1], true) || !in_array($oldEffect, [0, 1], true)) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_STATUS_UNAVAILABLE'));
            $allowed = $this->db->setQuery(Sql::query($this->db, 'SELECT to_status_id FROM #__dharmashop_status_transitions WHERE from_status_id=:id', [':id'=>[$expected,ParameterType::INTEGER]]))->loadColumn();
            if ($allowed && !in_array($statusId, array_map('intval', $allowed), true)) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_STATUS_UNAVAILABLE'));
            $now = Factory::getDate()->toSql();
            Sql::exec($this->db, 'UPDATE #__dharmashop_orders SET status_id=:s,modified=:now,modified_by=:by WHERE id=:id', [':s'=>[$statusId,ParameterType::INTEGER], ':now'=>[$now], ':by'=>[$actor,ParameterType::INTEGER], ':id'=>[$orderId,ParameterType::INTEGER]]);
            $history = (object) ['order_id'=>$orderId,'status_id'=>$statusId,'user_id'=>$actor,'comment'=>Text::_('COM_DHARMASHOP_ORDER_STATUS_HISTORY'),'notified'=>0,'created'=>$now];
            $this->db->insertObject('#__dharmashop_order_history', $history);
            $historyId = (int) $this->db->insertid();
            // Эффект статуса на остатки: «возвращает остатки» (отмена) возвращает списанное на склад, обратный переход списывает снова.
            if ((int) $status->stock_effect !== $oldEffect) (new StockService($this->db))->applyOrderEffect($orderId,(int) $status->stock_effect,$actor ?: null);
            $this->db->transactionCommit();
        } catch (\Throwable $error) { $this->db->transactionRollback(); throw $error; }
        $this->notifications->lifecycle('onDharmaShopOrderStatusChanged', ['order_id'=>$orderId,'history_id'=>$historyId,'previous_status_id'=>$expected,'status_id'=>$statusId]);
        $this->notifications->notify($historyId);
        return true;
    }
}
