<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\OrderAccess;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

/** Чтение своего снимка: доступ ограничен в SQL до загрузки состава и контактов. */
class OrderModel extends BaseDatabaseModel
{
    protected $option = 'com_dharmashop';

    public function getItem(?int $id = null): object
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $id ??= $app->getInput()->getInt('id');
        $identity = $app->getIdentity();
        $userId = $identity && !$identity->guest ? (int) $identity->id : 0;
        // Гостевой заказ по секретной ссылке из письма (`key` = orders.secret): доступ без сессии. Ключ не уходит дальше этой страницы.
        $key = (string) $app->getInput()->get('key', '', 'cmd');
        $byKey = preg_match('/^[a-f0-9]{32}$/', $key) === 1;
        if ($byKey) $app->setHeader('Referrer-Policy', 'no-referrer', true);
        if ($id < 1 || ($userId === 0 && !$byKey && !OrderAccess::allowsGuest($app, $id, $userId))) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_ORDER_NOT_FOUND'), 404);
        }
        $db = $this->getDatabase();
        $query = $db->createQuery()->select([
            'o.id', 'o.number', 'o.currency', 'o.subtotal', 'o.adjustments_total', 'o.total',
            'o.contacts', 'o.shipping_title', 'o.payment_title', 'o.created', 's.title AS status_title',
        ])->from($db->quoteName('#__dharmashop_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__dharmashop_statuses', 's') . ' ON s.id = o.status_id')
            ->where('o.id = :id')->bind(':id', $id, ParameterType::INTEGER);
        // Доступ: свой заказ вошедшего пользователя или гостевой заказ по секретному ключу (в том числе вошедшему, оформившему его гостем).
        if ($userId > 0 && $byKey) {
            $query->where('(o.user_id = :owner OR (o.user_id IS NULL AND o.secret = :secret))')->bind(':owner', $userId, ParameterType::INTEGER)->bind(':secret', $key);
        } elseif ($userId > 0) {
            $query->where('o.user_id = :owner')->bind(':owner', $userId, ParameterType::INTEGER);
        } else {
            $query->where('o.user_id IS NULL');
            if ($byKey) $query->where('o.secret = :secret')->bind(':secret', $key);
        }
        $order = $db->setQuery($query)->loadObject();
        if (!$order) throw new \RuntimeException(Text::_('COM_DHARMASHOP_ORDER_NOT_FOUND'), 404);
        $order->snapshot = json_decode((string) $order->contacts, true) ?: [];
        unset($order->contacts);
        foreach ([
            'items' => ['#__dharmashop_order_items', ['title', 'sku', 'unit', 'qty', 'price', 'total', 'params']],
            'adjustments' => ['#__dharmashop_order_adjustments', ['type', 'title', 'amount']],
        ] as $key => [$table, $columns]) {
            $query = $db->createQuery()->select($db->quoteName($columns))->from($db->quoteName($table))
                ->where('order_id = :id')->bind(':id', $id, ParameterType::INTEGER)->order('ordering, id');
            $order->$key = $db->setQuery($query)->loadObjectList();
        }
        return $order;
    }
}
