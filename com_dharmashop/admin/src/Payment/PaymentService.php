<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Payment;

use Dharma\Component\DharmaShop\Administrator\Notification\NotificationService;
use Dharma\Component\DharmaShop\Administrator\Order\OrderStatusService;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Оплата (C83): способы, доступные для корзины и способа доставки, выбор покупателя, начало оплаты после заказа и обработка ответов шлюза.
 * Цену не считает: наценок и скидок за способ оплаты в ядре нет (позже — по событию). Все суммы берутся из заказа на сервере.
 */
final class PaymentService
{
    /** Статусы оплаты заказа (`orders.payment_status`). */
    public const STATUS_NONE = 'none';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly OrderStatusService $statuses,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Опубликованные способы с доступным типом (плагин включён), по порядку, с учётом уровня доступа посетителя.
     *
     * @return  Method[]
     */
    public function methods(): array
    {
        $identity = Factory::getApplication()->getIdentity();
        $levels   = $identity ? array_map('intval', $identity->getAuthorisedViewLevels()) : [1];
        $query    = $this->db->createQuery()
            ->select(['id', 'title', 'plugin', 'description', 'image', 'params', 'is_default', 'paid_status_id'])
            ->from($this->db->quoteName('#__dharmashop_payment_methods'))
            ->where('state = 1')
            ->whereIn('access', $levels)
            ->order('ordering, id');
        $registry = TypeRegistry::current();
        $methods  = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            if ($registry->find((string) $row->plugin) !== null) {
                $methods[] = Method::fromRow($row);
            }
        }

        return $methods;
    }

    /** Есть ли хотя бы один способ: тогда выбор оплаты обязателен при оформлении. */
    public function hasMethods(): bool
    {
        return $this->methods() !== [];
    }

    /**
     * Способы, доступные для этой корзины (тип разрешил), с учётом выбранной доставки.
     *
     * @param   array<int, int[]>  $allowedByShipping  [id доставки => id разрешённых способов оплаты]; пусто или нет ключа — любые
     *
     * @return  array<int, array{method: Method, type: TypeInterface, info: array<int, array{label: string, value: string}>}>  ключ — id способа
     */
    public function options(Context $context, array $allowedByShipping = []): array
    {
        $registry = TypeRegistry::current();
        $allowed  = (array) ($allowedByShipping[$context->shippingId] ?? []);
        $options  = [];

        foreach ($this->methods() as $method) {
            $type = $registry->find($method->plugin);

            if ($type === null || ($allowed !== [] && !in_array($method->id, $allowed, true)) || !$type->available($method, $context)) {
                continue;
            }

            $options[$method->id] = ['method' => $method, 'type' => $type, 'info' => self::cleanRows($type->describe($method, $context))];
        }

        return $options;
    }

    /**
     * Проверяет выбор покупателя: способ доступен для корзины и доставки, данные корректны.
     *
     * @param   array<int, int[]>      $allowedByShipping  см. options()
     * @param   array<string, mixed>   $input              значения из `payment[<id>]`
     *
     * @throws  PaymentInputException
     */
    public function resolve(int $methodId, Context $context, array $input, array $allowedByShipping = []): Selection
    {
        $options = $this->options($context, $allowedByShipping);

        if (!isset($options[$methodId])) {
            throw new PaymentInputException(Text::_('COM_DHARMASHOP_PAYMENT_UNAVAILABLE'));
        }

        $option = $options[$methodId];
        $result = $option['type']->validateInput($option['method'], $context, $input);

        if ($result['errors']) {
            throw new PaymentInputException(Text::_('COM_DHARMASHOP_PAYMENT_FIELDS_ERROR'), $result['errors']);
        }

        return new Selection($option['method'], $result['data'], self::cleanRows(array_merge($option['info'], $result['rows'])));
    }

    /** Динамический запрос типа с оформления. @return array<string, mixed> */
    public function ajax(int $methodId, Context $context, string $action, array $input, array $allowedByShipping = []): array
    {
        $options = $this->options($context, $allowedByShipping);

        if (!isset($options[$methodId])) {
            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_PAYMENT_UNAVAILABLE')];
        }

        return $options[$methodId]['type']->ajax($options[$methodId]['method'], $context, $action, $input);
    }

    /** Идентификатор способа по умолчанию среди доступных (для предварительного выбора). */
    public function defaultId(array $options): int
    {
        foreach ($options as $id => $option) {
            if ($option['method']->isDefault) {
                return (int) $id;
            }
        }

        return (int) (array_key_first($options) ?? 0);
    }

    /**
     * Начинает оплату только что созданного заказа: тип решает — оплата вне сайта, шлюз или страница сайта.
     * Статус оплаты заказа: «ожидает» для шлюза и страницы, «нет» для оплаты вне сайта. Ошибка типа не отменяет заказ.
     */
    public function initiate(int $orderId): Initiation
    {
        $order = $this->loadOrder($orderId);

        if ($order === null || (int) $order->payment_method_id < 1) {
            return Initiation::none();
        }

        [$method, $type] = $this->methodAndType((int) $order->payment_method_id);

        if ($method === null || $type === null) {
            return Initiation::none();
        }

        $payment = $this->orderPayment($order);
        $urls    = new Urls((int) $order->id, $method->id, (string) $order->secret);

        try {
            $initiation = $type->initiate($method, $payment, $urls);
        } catch (\Throwable $error) {
            Log::add('Payment initiate ' . $method->plugin . ': ' . get_class($error), Log::ERROR, 'com_dharmashop');

            return Initiation::none();
        }

        $status = $initiation->kind === Initiation::NONE ? self::STATUS_NONE : self::STATUS_PENDING;
        $data   = (array) (json_decode((string) $order->payment_data, true) ?: []);

        if ($initiation->data) {
            $data['data'] = array_merge((array) ($data['data'] ?? []), $initiation->data);
        }

        Sql::exec($this->db, 'UPDATE #__dharmashop_orders SET payment_status = :s, payment_data = :d WHERE id = :id', [
            ':s' => [$status],
            ':d' => [json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ':id' => [(int) $order->id, ParameterType::INTEGER],
        ]);
        if ($initiation->kind !== Initiation::NONE) {
            $this->transaction((int) $order->id, $method->id, self::STATUS_PENDING, (float) $order->total, (string) $order->currency, '', '');
        }

        return $initiation;
    }

    /**
     * Данные для страницы оплаты на сайте: заказ, способ, тип. Доступ к заказу проверяет вызывающий (ключ заказа или владелец).
     *
     * @return  array{order: OrderPayment, method: Method, type: TypeInterface, urls: Urls, info: array<int, array{label: string, value: string}>}|null
     */
    public function pageData(int $orderId): ?array
    {
        $order = $this->loadOrder($orderId);

        if ($order === null || (int) $order->payment_method_id < 1) {
            return null;
        }

        [$method, $type] = $this->methodAndType((int) $order->payment_method_id, false);

        if ($method === null || $type === null) {
            return null;
        }

        $payment = $this->orderPayment($order);
        $context = new Context((float) $order->total, (float) $order->subtotal, 0.0, 0, (string) $order->currency);

        return [
            'order' => $payment,
            'method' => $method,
            'type' => $type,
            'urls' => new Urls((int) $order->id, $method->id, (string) $order->secret),
            'info' => self::cleanRows($type->describe($method, $context)),
        ];
    }

    /**
     * Обратный вызов шлюза: тип проверяет подлинность и возвращает итог, ядро применяет его.
     *
     * @param   array<string, mixed>  $input
     */
    public function handle(int $methodId, int $orderId, string $action, array $input): Outcome
    {
        $order = $this->loadOrder($orderId);

        // Заказ должен быть оплачиваемым именно этим способом: чужой способ не может менять статус.
        if ($order === null || (int) $order->payment_method_id !== $methodId) {
            return Outcome::ignored('Not found', 404);
        }

        [$method, $type] = $this->methodAndType($methodId, false);

        if ($method === null || $type === null) {
            return Outcome::ignored('Not found', 404);
        }

        try {
            $outcome = $type->handle($method, $this->orderPayment($order), $action, $input);
        } catch (\Throwable $error) {
            Log::add('Payment handle ' . $method->plugin . ': ' . get_class($error), Log::ERROR, 'com_dharmashop');

            return Outcome::ignored('Error', 500);
        }

        if ($outcome->status !== Outcome::IGNORED) {
            $this->apply((int) $order->id, $method, $outcome);
        }

        return $outcome;
    }

    /**
     * Применяет итог платежа: статус оплаты заказа, журнал, статус заказа «после оплаты» (если задан у способа) и событие.
     * Повторный вызов безопасен: оплаченный заказ не откатывается.
     */
    public function apply(int $orderId, Method $method, Outcome $outcome): void
    {
        $order = $this->loadOrder($orderId);

        if ($order === null) {
            return;
        }

        $previous = (string) $order->payment_status;
        $new      = match ($outcome->status) {
            Outcome::PAID => self::STATUS_PAID,
            Outcome::FAILED => self::STATUS_FAILED,
            Outcome::CANCELLED => self::STATUS_CANCELLED,
            default => self::STATUS_PENDING,
        };

        // Оплаченный заказ остаётся оплаченным; ошибка или отказ после оплаты (повторный вызов) игнорируются.
        if ($previous === self::STATUS_PAID && $new !== self::STATUS_PAID) {
            return;
        }

        $this->transaction($orderId, $method->id, $new, $outcome->amount > 0 ? $outcome->amount : (float) $order->total, (string) $order->currency, $outcome->ref, $outcome->message);

        if ($previous === $new) {
            return;
        }

        $now = Factory::getDate()->toSql();
        Sql::exec($this->db, 'UPDATE #__dharmashop_orders SET payment_status = :s, paid = :p WHERE id = :id', [
            ':s' => [$new],
            ':p' => [$new === self::STATUS_PAID ? $now : null, $new === self::STATUS_PAID ? ParameterType::STRING : ParameterType::NULL],
            ':id' => [$orderId, ParameterType::INTEGER],
        ]);

        if ($new === self::STATUS_PAID && $method->paidStatusId > 0 && (int) $order->status_id !== $method->paidStatusId) {
            try {
                // Комментарий истории берётся из языкового файла админки: на сайте он не загружен.
                Factory::getApplication()->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
                $this->statuses->change($orderId, $method->paidStatusId, (int) $order->status_id, 0);
            } catch (\Throwable $error) {
                // Оплата уже зафиксирована; статус заказа менеджер поменяет вручную.
                Log::add('Payment paid status ' . $orderId . ': ' . get_class($error), Log::WARNING, 'com_dharmashop');
            }
        }

        $this->notifications->lifecycle('onDharmaShopPaymentStatusChanged', ['order_id' => $orderId, 'method_id' => $method->id, 'previous' => $previous, 'status' => $new]);
    }

    /**
     * Строки для показа: только подпись и значение обычным текстом, без пустых и слишком длинных.
     *
     * @param   array<int, mixed>  $rows
     *
     * @return  array<int, array{label: string, value: string}>
     */
    public static function cleanRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $label = mb_substr(trim(strip_tags((string) ($row['label'] ?? ''))), 0, 100);
            $value = mb_substr(trim(strip_tags((string) ($row['value'] ?? ''))), 0, 2000);

            if ($label !== '' && $value !== '') {
                $clean[] = ['label' => $label, 'value' => $value];
            }
        }

        return $clean;
    }

    /** Карта «способ доставки → разрешённые способы оплаты» (пусто = любые) по всем опубликованным способам доставки. @return array<int, int[]> */
    public function allowedByShipping(): array
    {
        $map = [];

        foreach ((array) $this->db->setQuery('SELECT id, payment_ids FROM ' . $this->db->quoteName('#__dharmashop_shipping_methods') . ' WHERE state = 1')->loadObjectList() as $row) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $row->payment_ids))));
            $map[(int) $row->id] = $ids;
        }

        return $map;
    }

    private function loadOrder(int $orderId): ?object
    {
        $query = Sql::query($this->db, 'SELECT id, number, secret, status_id, currency, subtotal, total, contact_name, contact_email, contact_phone, payment_method_id, payment_title, payment_data, payment_status '
            . 'FROM #__dharmashop_orders WHERE id = :id', [':id' => [$orderId, ParameterType::INTEGER]]);

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    private function orderPayment(object $order): OrderPayment
    {
        $data = (array) (json_decode((string) $order->payment_data, true) ?: []);

        return new OrderPayment(
            (int) $order->id,
            (string) $order->number,
            (string) $order->secret,
            (float) $order->total,
            (string) $order->currency,
            (string) $order->payment_status,
            (string) $order->contact_name,
            (string) $order->contact_email,
            (string) $order->contact_phone,
            (array) ($data['data'] ?? []),
        );
    }

    /**
     * Способ и его тип по id. Выключенный способ тоже подходит для уже созданного заказа (оплата не должна пропасть из-за снятия с публикации).
     *
     * @return  array{0: ?Method, 1: ?TypeInterface}
     */
    private function methodAndType(int $methodId, bool $publishedOnly = true): array
    {
        $query = Sql::query($this->db, 'SELECT id, title, plugin, description, image, params, is_default, paid_status_id FROM #__dharmashop_payment_methods WHERE id = :id'
            . ($publishedOnly ? ' AND state = 1' : ''), [':id' => [$methodId, ParameterType::INTEGER]]);
        $row = $this->db->setQuery($query)->loadObject();

        if (!$row) {
            return [null, null];
        }

        return [Method::fromRow($row), TypeRegistry::current()->find((string) $row->plugin)];
    }

    /**
     * Записывает платёж в `payments`: обновляет последнюю попытку этого заказа и способа либо создаёт новую. `external_id` (идентификатор шлюза)
     * вместе со способом уникален: повторный вызов шлюза не создаёт дубль.
     */
    private function transaction(int $orderId, int $methodId, string $status, float $amount, string $currency, string $ref, string $message): void
    {
        $now = Factory::getDate()->toSql();
        $ref = mb_substr($ref, 0, 100);

        try {
            $id = (int) $this->db->setQuery(Sql::query($this->db, 'SELECT id FROM #__dharmashop_payments WHERE order_id = :o AND method_id = :m ORDER BY id DESC', [
                ':o' => [$orderId, ParameterType::INTEGER],
                ':m' => [$methodId, ParameterType::INTEGER],
            ]), 0, 1)->loadResult();
            $data = json_encode($message !== '' ? ['message' => mb_substr($message, 0, 255)] : new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($id > 0) {
                Sql::exec($this->db, 'UPDATE #__dharmashop_payments SET state = :s, amount = :a, external_id = COALESCE(:e, external_id), data = :d, modified = :now WHERE id = :id', [
                    ':s' => [mb_substr($status, 0, 20)],
                    ':a' => [$amount],
                    ':e' => [$ref !== '' ? $ref : null, $ref !== '' ? ParameterType::STRING : ParameterType::NULL],
                    ':d' => [$data],
                    ':now' => [$now],
                    ':id' => [$id, ParameterType::INTEGER],
                ]);

                return;
            }

            Sql::exec($this->db, 'INSERT INTO #__dharmashop_payments (order_id, method_id, amount, currency, state, external_id, data, created) VALUES (:o, :m, :a, :cur, :s, :e, :d, :now)', [
                ':o' => [$orderId, ParameterType::INTEGER],
                ':m' => [$methodId, ParameterType::INTEGER],
                ':a' => [$amount],
                ':cur' => [mb_substr($currency, 0, 3)],
                ':s' => [mb_substr($status, 0, 20)],
                ':e' => [$ref !== '' ? $ref : null, $ref !== '' ? ParameterType::STRING : ParameterType::NULL],
                ':d' => [$data],
                ':now' => [$now],
            ]);
        } catch (\Throwable $error) {
            // Дубль внешнего идентификатора или сбой журнала не должен ломать обработку платежа.
            Log::add('Payment transaction: ' . get_class($error), Log::WARNING, 'com_dharmashop');
        }
    }
}
