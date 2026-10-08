<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Список заказов вошедшего пользователя (личный кабинет): только заказы, оформленные под его учётной записью (`orders.user_id`).
 * Гостевые заказы сюда не попадают (они доступны только в сессии, где оформлены). Состав заказа не читается — только итоги.
 */
class OrdersModel extends BaseDatabaseModel
{
    protected $option = 'com_dharmashop';

    /** Номер вошедшего пользователя; 0 — гость. */
    public function getUserId(): int
    {
        $identity = Factory::getApplication()->getIdentity();

        return $identity && !$identity->guest ? (int) $identity->id : 0;
    }

    /**
     * Привязывает к аккаунту гостевые заказы с тем же e-mail (настройка «Привязывать гостевые заказы»): «verified» — только если почта
     * аккаунта подтверждена (активация по ссылке из письма включена и пройдена), «any» — по любому совпадению (небезопасно: чужую почту
     * можно указать при регистрации без подтверждения). Один индексированный UPDATE, выполняется один раз за сессию.
     */
    private function claimGuestOrders(): void
    {
        $app = Factory::getApplication();
        $mode = (string) ComponentHelper::getParams('com_dharmashop')->get('orders_claim', 'verified');
        $user = $app->getIdentity();

        if ($mode === 'off' || !$user || $user->guest || $app->getUserState('com_dharmashop.orders.claimed') === (int) $user->id) {
            return;
        }

        $app->setUserState('com_dharmashop.orders.claimed', (int) $user->id);
        $verified = (int) ComponentHelper::getParams('com_users')->get('useractivation', 0) === 1 && (string) $user->activation === '' && (int) $user->block === 0;

        if ($mode === 'verified' && !$verified) {
            return;
        }

        $email = trim((string) $user->email);
        $userId = (int) $user->id;

        if ($email === '') {
            return;
        }

        $db = $this->getDatabase();
        $query = $db->createQuery()->update($db->quoteName('#__dharmashop_orders'))->set($db->quoteName('user_id') . ' = :user')
            ->where($db->quoteName('user_id') . ' IS NULL')->where($db->quoteName('contact_email') . ' = :email')
            ->bind(':user', $userId, ParameterType::INTEGER)->bind(':email', $email);
        $db->setQuery($query)->execute();
    }

    public function getTotal(): int
    {
        if ($this->getUserId() === 0) {
            return 0;
        }

        $this->claimGuestOrders();

        return (int) $this->getDatabase()->setQuery($this->baseQuery()->select('COUNT(*)'))->loadResult();
    }

    /**
     * Страница заказов, новые первыми.
     *
     * @return  object[]  id, number, currency, total, created, status_title, status_color, items_count
     */
    public function getOrders(int $limit, int $start = 0): array
    {
        if ($this->getUserId() === 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $this->baseQuery()
            ->select([
                'o.id', 'o.number', 'o.currency', 'o.total', 'o.created', 's.title AS status_title', 's.color AS status_color',
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_order_items') . ' i WHERE i.order_id = o.id) AS items_count',
            ])
            ->order('o.id DESC');

        return $db->setQuery($query, max(0, $start), max(1, $limit))->loadObjectList();
    }

    private function baseQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $userId = $this->getUserId();

        return $db->createQuery()->from($db->quoteName('#__dharmashop_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__dharmashop_statuses', 's') . ' ON s.id = o.status_id')
            ->where('o.user_id = :owner')->bind(':owner', $userId, ParameterType::INTEGER);
    }
}
