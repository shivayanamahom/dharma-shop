<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Order;

use Dharma\Component\DharmaShop\Administrator\Cart\CartService;
use Dharma\Component\DharmaShop\Administrator\Customer\CustomerService;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Dharma\Component\DharmaShop\Administrator\Notification\NotificationService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Базовая запись снимка заказа. Доставка/оплата и эффекты статусов подключаются последующими этапами. */
final class OrderService
{
    public function __construct(private readonly DatabaseInterface $db, private readonly CartService $carts, private readonly CustomerService $customers, private readonly NotificationService $notifications) {}

    /** Начальный статус нейтральный: создаётся только если справочник ещё пуст. */
    public function ensureInitialStatus(): int
    {
        $query = $this->db->createQuery()->select('id')->from($this->db->quoteName('#__dharmashop_statuses'))->where('state=1 AND is_default=1 AND stock_effect=0')->order('ordering,id');
        $id = (int) $this->db->setQuery($query,0,1)->loadResult();
        if ($id) return $id;
        $count = (int) $this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->quoteName('#__dharmashop_statuses'))->loadResult();
        if ($count > 0) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_STATUS_MISSING'));
        Sql::exec($this->db, 'INSERT INTO #__dharmashop_statuses (title,alias,color,is_default,stock_effect,ordering,state) VALUES (:t,:a,:c,1,0,1,1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            [':t'=>[Text::_('COM_DHARMASHOP_ORDER_STATUS_NEW')],':a'=>['new'],':c'=>['info']]);
        return (int) $this->db->insertid();
    }
    /** Ключ заранее проверяется по сессии контроллером; его уникальность защищает повтор POST. */
    public function create(object $cart, int $userId, string $key, array $validated, Registry $settings): array
    {
        if ((int) $settings->get('catalog_mode',0) === 1 || ($userId === 0 && (int) $settings->get('checkout_guest',1) !== 1)) {
            throw new \DomainException(Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN_REQUIRED'));
        }
        if ($validated['errors'] || !preg_match('/^[a-f0-9]{32}$/',$key)) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_INVALID'));
        $this->db->transactionStart();
        try {
            $locked = $this->db->setQuery(Sql::query($this->db,'SELECT id,user_id FROM #__dharmashop_carts WHERE id=:id FOR UPDATE', [':id'=>[(int) $cart->id,ParameterType::INTEGER]]))->loadObject();
            if (!$locked || ((int) $locked->user_id > 0 && (int) $locked->user_id !== $userId)) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_INVALID'));
            $existing = $this->db->setQuery(Sql::query($this->db,'SELECT id,number FROM #__dharmashop_orders WHERE secret=:s', [':s'=>[$key]]))->loadAssoc();
            if ($existing) { $this->db->transactionCommit(); return $existing; }
            // Блокируем строки и диапазон индекса cart_id перед чтением снимка корзины.
            $this->db->setQuery(Sql::query($this->db,'SELECT id FROM #__dharmashop_cart_items WHERE cart_id=:id FOR UPDATE', [':id'=>[(int) $cart->id,ParameterType::INTEGER]]))->loadColumn();
            $lines = $this->carts->lines($cart);
            if (!$lines) throw new \DomainException(Text::_('COM_DHARMASHOP_CART_EMPTY'));
            $quantities = [];
            foreach ($lines as $line) {
                if ($line->error !== '') throw new \DomainException($line->title . ': ' . Text::_($line->error));
                if (!is_finite($line->qty) || $line->qty <= 0 || $line->unit_price === null || !is_finite($line->unit_price) || $line->unit_price < 0) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_INVALID'));
                $quantities[$line->variant_id] = ($quantities[$line->variant_id] ?? 0.0) + $line->qty;
            }
            foreach ($lines as $line) {
                if ((int) $line->stock_tracked === 1 && (int) $line->backorder !== 1 && $quantities[$line->variant_id] > (float) $line->stock_total + 1e-9) throw new \DomainException($line->title . ': ' . Text::_('COM_DHARMASHOP_CART_ERROR_STOCK'));
            }
            $totals = $this->carts->totals($cart,$lines);
            if (!is_finite($totals['total'])) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_INVALID'));
            $now = Factory::getDate()->toSql();
            $statusId = $this->ensureInitialStatus();
            $customerId = $this->customers->forOrder($userId,$validated['profile'],$now);
            $snapshot = $validated['snapshot'];
            foreach ($snapshot['consents'] as &$consent) $consent['accepted_at'] = $consent['accepted'] ? $now : null;
            unset($consent);
            $profile = $validated['profile'];
            $adjustmentsTotal = round(array_sum(array_column($totals['adjustments'],'amount')),2);
            $order = (object) ['number'=>null,'secret'=>$key,'kind'=>1,'customer_id'=>$customerId,'user_id'=>$userId ?: null,'status_id'=>$statusId,
                'currency'=>(string) $settings->get('currency_code','RUB'),'subtotal'=>$totals['subtotal'],'adjustments_total'=>$adjustmentsTotal,'total'=>$totals['total'],
                'contact_name'=>$profile['name'],'contact_email'=>$profile['email'],'contact_phone'=>$profile['phone'],
                'contacts'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),'cart_id'=>(int) $cart->id,'created'=>$now,'created_by'=>$userId];
            $this->db->insertObject('#__dharmashop_orders',$order);
            $id = (int) $this->db->insertid(); $number = str_pad((string) $id,6,'0',STR_PAD_LEFT);
            Sql::exec($this->db,'UPDATE #__dharmashop_orders SET number=:n WHERE id=:id', [':n'=>[$number],':id'=>[$id,ParameterType::INTEGER]]);
            foreach ($lines as $index=>$line) {
                $item = (object) ['order_id'=>$id,'variant_id'=>(int) $line->variant_id,'product_id'=>(int) $line->product_id,'sku'=>(string) $line->sku,
                    'title'=>(string) $line->title,'unit'=>(string) $line->unit,'qty'=>$line->qty,'price'=>$line->unit_price,'total'=>$line->total,
                    'params'=>json_encode(['variant_title'=>$line->variant_title,'base_price'=>$line->base_price,'line_params'=>$line->line_params],JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),'ordering'=>$index+1];
                $this->db->insertObject('#__dharmashop_order_items',$item);
            }
            foreach ($totals['adjustments'] as $index=>$row) {
                $amount = (float) $row['amount'];
                if (!is_finite($amount)) throw new \DomainException(Text::_('COM_DHARMASHOP_ORDER_INVALID'));
                $type = (string) ($row['type'] ?? ($amount < 0 ? 'discount' : 'fee'));
                if (!in_array($type,['shipping','discount','tax','fee'],true)) $type = 'fee';
                $adjustment = (object) ['order_id'=>$id,'type'=>$type,'code'=>mb_substr((string) ($row['code'] ?? 'cart_' . $index),0,100),
                    'title'=>mb_substr((string) $row['title'],0,255),'amount'=>$amount,'ordering'=>$index+1];
                $this->db->insertObject('#__dharmashop_order_adjustments',$adjustment);
            }
            $history = (object) ['order_id'=>$id,'status_id'=>$statusId,'user_id'=>$userId ?: null,'comment'=>Text::_('COM_DHARMASHOP_ORDER_CREATED_HISTORY'),'notified'=>0,'created'=>$now];
            $this->db->insertObject('#__dharmashop_order_history',$history);
            $historyId = (int) $this->db->insertid();
            Sql::exec($this->db,'UPDATE #__dharmashop_carts SET customer_id=:cu WHERE id=:id', [':cu'=>[$customerId,ParameterType::INTEGER],':id'=>[(int) $cart->id,ParameterType::INTEGER]]);
            $this->carts->clear($cart);
            $this->db->transactionCommit();
        } catch (\Throwable $error) { $this->db->transactionRollback(); throw $error; }
        $this->notifications->notify($historyId);
        $this->notifications->lifecycle('onDharmaShopOrderAfterCreate', ['order_id'=>$id,'history_id'=>$historyId]);
        return ['id'=>$id,'number'=>$number];
    }
}
