<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

/** Просмотр сохранённого снимка. Данные корзины и текущие цены не подмешиваются. */
class OrderModel extends BaseDatabaseModel
{
    protected $option = 'com_dharmashop';
    public function getItem(): ?object
    {
        $id = Factory::getApplication()->getInput()->getInt('id');
        $db = $this->getDatabase();
        $query = $db->createQuery()->select(['o.id','o.number','o.secret','o.kind','o.customer_id','o.user_id','o.status_id','o.currency','o.subtotal','o.adjustments_total','o.total',
            'o.contact_name','o.contact_email','o.contact_phone','o.contacts','o.note','o.shipping_title','o.shipping_data','o.payment_title','o.payment_data','o.payment_status','o.paid','o.created','s.title AS status_title','s.color AS status_color'])
            ->from($db->quoteName('#__dharmashop_orders','o'))->join('LEFT',$db->quoteName('#__dharmashop_statuses','s') . ' ON s.id=o.status_id')
            ->where('o.id=:id')->bind(':id',$id,ParameterType::INTEGER);
        $order = $db->setQuery($query)->loadObject();
        if (!$order) return null;
        $order->snapshot = json_decode((string) $order->contacts,true) ?: [];
        $order->shipping = json_decode((string) $order->shipping_data,true) ?: [];
        $order->payment = json_decode((string) $order->payment_data,true) ?: [];
        foreach (['items'=>['#__dharmashop_order_items',['id','product_id','variant_id','sku','title','unit','qty','price','total','params'],'ordering,id'],
            'adjustments'=>['#__dharmashop_order_adjustments',['type','code','title','amount'],'ordering,id']] as $key=>[$table,$columns,$sorting]) {
            $query = $db->createQuery()->select($db->quoteName($columns))->from($db->quoteName($table))
                ->where('order_id=:id')->bind(':id',$id,ParameterType::INTEGER)->order($sorting);
            $order->$key = $db->setQuery($query)->loadObjectList();
        }
        $query = $db->createQuery()->select(['h.id','h.created','h.comment','h.notified','s.title AS status_title','u.name AS author'])
            ->from($db->quoteName('#__dharmashop_order_history','h'))->join('LEFT',$db->quoteName('#__dharmashop_statuses','s') . ' ON s.id=h.status_id')
            ->join('LEFT',$db->quoteName('#__users','u') . ' ON u.id=h.user_id')->where('h.order_id=:id')->bind(':id',$id,ParameterType::INTEGER)->order('h.created,h.id');
        $order->history = $db->setQuery($query)->loadObjectList();
        $query = $db->createQuery()->select(['history_id', 'SUM(state=1) AS sent_count', 'SUM(state=2) AS failed_count', 'SUM(state=0) AS pending_count'])
            ->from($db->quoteName('#__dharmashop_notifications'))->where('order_id=:id')->bind(':id',$id,ParameterType::INTEGER)->group('history_id');
        $deliveries = $db->setQuery($query)->loadObjectList('history_id');
        foreach ($order->history as $entry) $entry->delivery = $deliveries[$entry->id] ?? null;
        return $order;
    }
}
