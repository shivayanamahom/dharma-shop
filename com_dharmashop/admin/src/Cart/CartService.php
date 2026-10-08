<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Cart;

use Dharma\Component\DharmaShop\Administrator\Order\OrderRules;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Корзина (ARCHITECTURE §5.7). Строка корзины — вариант товара; цена в корзине не хранится и берётся из текущей цены варианта
 * при каждом чтении. Корзина гостя определяется токеном из cookie, корзина вошедшего пользователя — его user_id (при входе
 * корзина гостя сливается с ней). Количество проверяется по правилам оформления (минимум, шаг, максимум) и остатку; плагины группы
 * `dharmashop` могут отклонить или изменить позицию (события onDharmaShopCartBeforeAdd / AfterAdd / ItemValidate / Totals).
 *
 * Ошибки возвращаются языковыми ключами `COM_DHARMASHOP_CART_ERROR_*` (языковой файл сайта).
 */
final class CartService
{
    /** Срок жизни корзины, дней с последнего изменения. */
    public const LIFETIME_DAYS = 30;

    public function __construct(private readonly DatabaseInterface $db, private readonly OrderRules $rules)
    {
    }

    /**
     * Находит корзину: вошедшего пользователя или по токену гостя. Корзина гостя при входе сливается с корзиной пользователя.
     */
    public function find(?string $token, int $userId): ?object
    {
        $byToken = $token !== null && preg_match('/^[a-f0-9]{32}$/D', $token) ? $this->fetchBy('token', $token) : null;

        if ($userId <= 0) {
            return $byToken !== null && $byToken->user_id === null ? $byToken : null;
        }

        $byUser = $this->fetchBy('user_id', (string) $userId);

        if ($byUser === null) {
            // Вошёл пользователь без своей корзины: корзина гостя становится его корзиной.
            if ($byToken !== null && $byToken->user_id === null) {
                $this->execute('UPDATE #__dharmashop_carts SET user_id = :u WHERE id = :c', [':u' => [$userId, ParameterType::INTEGER], ':c' => [(int) $byToken->id, ParameterType::INTEGER]]);
                $byToken->user_id = (string) $userId;

                return $byToken;
            }

            return null;
        }

        if ($byToken !== null && $byToken->id !== $byUser->id && $byToken->user_id === null) {
            $this->merge($byToken, $byUser);
        }

        return $byUser;
    }

    /** Создаёт пустую корзину с новым токеном. */
    public function create(int $userId): object
    {
        $token = bin2hex(random_bytes(16));
        $now = $this->now();
        $expires = $this->expires();
        $user = $userId > 0 ? $userId : null;
        $query = $this->db->createQuery()->insert($this->db->quoteName('#__dharmashop_carts'))
            ->columns($this->db->quoteName(['token', 'user_id', 'price_list_id', 'created', 'modified', 'expires']))
            ->values(':token, :user, 1, :created, :modified, :expires')
            ->bind(':token', $token)->bind(':user', $user, $user === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':created', $now)->bind(':modified', $now)->bind(':expires', $expires);
        $this->db->setQuery($query)->execute();

        return $this->fetchBy('token', $token);
    }

    /**
     * Добавляет товар (вариант по умолчанию или заданный вариант) в корзину.
     *
     * @param   array<string, mixed>  $params  дополнительные параметры строки (например, срок аренды); строки с разными параметрами раздельны
     *
     * @return  array{ok: bool, error?: string, item_id?: int}
     */
    public function add(object $cart, int $productId, ?int $variantId, float $qty, array $params = []): array
    {
        $variant = $this->variantRow($productId, $variantId);

        if ($variant === null || !$this->purchasable((int) $variant['product_id'])) {
            return ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_UNAVAILABLE'];
        }

        $qty = $this->normalise($qty);
        $event = $this->dispatch('onDharmaShopCartBeforeAdd', ['cart' => $cart, 'variant' => $variant, 'qty' => $qty, 'params' => $params, 'error' => '']);

        if ((string) $event->getArgument('error') !== '') {
            return ['ok' => false, 'error' => (string) $event->getArgument('error')];
        }

        $qty = $this->normalise((float) $event->getArgument('qty'));
        $params = (array) $event->getArgument('params');
        $hash = $this->lineHash((int) $variant['id'], $params);
        $existing = $this->db->setQuery($this->db->createQuery()->select($this->db->quoteName(['id', 'qty']))
            ->from($this->db->quoteName('#__dharmashop_cart_items'))->where('cart_id = :c')->where('line_hash = :h')
            ->bind(':c', $cart->id, ParameterType::INTEGER)->bind(':h', $hash))->loadObject();
        $total = $this->normalise($qty + ($existing ? (float) $existing->qty : 0.0));
        $error = $this->validateQty($variant, $total, $params);

        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        if ($existing) {
            $this->execute('UPDATE #__dharmashop_cart_items SET qty = :q WHERE id = :i', [':q' => [(string) $total], ':i' => [(int) $existing->id, ParameterType::INTEGER]]);
            $itemId = (int) $existing->id;
        } else {
            $encoded = $params ? json_encode($params, JSON_UNESCAPED_UNICODE) : null;
            $query = $this->db->createQuery()->insert($this->db->quoteName('#__dharmashop_cart_items'))
                ->columns($this->db->quoteName(['cart_id', 'variant_id', 'qty', 'line_hash', 'params']))
                ->values(':cart, :variant, :qty, :hash, :params')
                ->bind(':cart', $cart->id, ParameterType::INTEGER)->bind(':variant', $variant['id'], ParameterType::INTEGER)
                ->bind(':qty', $total)->bind(':hash', $hash)->bind(':params', $encoded, $encoded === null ? ParameterType::NULL : ParameterType::STRING);
            $this->db->setQuery($query)->execute();
            $itemId = (int) $this->db->insertid();
        }

        $this->touch((int) $cart->id);
        $this->dispatch('onDharmaShopCartAfterAdd', ['cart' => $cart, 'variant' => $variant, 'qty' => $total, 'item_id' => $itemId]);

        return ['ok' => true, 'item_id' => $itemId, 'qty' => $total];
    }

    /**
     * Что из товаров уже в корзине: товар → [строка, количество] (по строкам без дополнительных параметров; у товара с несколькими
     * вариантами — первая строка и общее количество). Нужно карточкам и странице товара, чтобы вместо кнопки показать виджет количества.
     *
     * @return  array<int, array{item: int, qty: float}>
     */
    public function productQuantities(object $cart): array
    {
        $query = $this->db->createQuery()
            ->select(['v.product_id', 'MIN(ci.id) AS item_id', 'SUM(ci.qty) AS qty_sum'])
            ->from($this->db->quoteName('#__dharmashop_cart_items', 'ci'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id')
            ->where('ci.cart_id = :c')->group('v.product_id')->bind(':c', $cart->id, ParameterType::INTEGER);
        $result = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $result[(int) $row->product_id] = ['item' => (int) $row->item_id, 'qty' => (float) $row->qty_sum];
        }

        return $result;
    }

    /**
     * Меняет количество строки (0 — удалить).
     *
     * @return  array{ok: bool, error?: string}
     */
    public function setQty(object $cart, int $itemId, float $qty): array
    {
        $item = $this->itemRow((int) $cart->id, $itemId);

        if ($item === null) {
            return ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_LINE'];
        }

        $qty = $this->normalise($qty);

        if ($qty <= 0) {
            return $this->remove($cart, $itemId) ? ['ok' => true] : ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_LINE'];
        }

        $variant = $this->variantRow((int) $item['product_id'], (int) $item['variant_id']);

        if ($variant === null) {
            return ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_UNAVAILABLE'];
        }

        $error = $this->validateQty($variant, $qty, (array) json_decode((string) $item['params'], true));

        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $this->execute('UPDATE #__dharmashop_cart_items SET qty = :q WHERE id = :i', [':q' => [(string) $qty], ':i' => [$itemId, ParameterType::INTEGER]]);
        $this->touch((int) $cart->id);

        return ['ok' => true];
    }

    public function remove(object $cart, int $itemId): bool
    {
        $this->execute('DELETE FROM #__dharmashop_cart_items WHERE id = :i AND cart_id = :c', [':i' => [$itemId, ParameterType::INTEGER], ':c' => [(int) $cart->id, ParameterType::INTEGER]]);
        $removed = $this->db->getAffectedRows() > 0;
        $this->touch((int) $cart->id);

        return $removed;
    }

    public function clear(object $cart): void
    {
        $this->execute('DELETE FROM #__dharmashop_cart_items WHERE cart_id = :c', [':c' => [(int) $cart->id, ParameterType::INTEGER]]);
        $this->touch((int) $cart->id);
    }

    /**
     * Строки корзины с текущими ценой, остатком и ошибками. Строка с ошибкой (`error` не пустая) в итоги не входит.
     *
     * @return  array<int, object>
     */
    public function lines(object $cart): array
    {
        $query = $this->db->createQuery()
            ->select([
                'ci.id', 'ci.variant_id', 'ci.qty', 'ci.params AS line_params', 'v.product_id', 'v.sku', 'v.title AS variant_title', 'v.state AS variant_state',
                'v.stock_tracked', 'v.backorder', 'v.stock_total', 'v.in_stock',
                'p.title', 'p.catid', 'c.title AS category_title', 'p.cover', 'p.cover_alt', 'p.state AS product_state', 'p.unit', 'p.qty_min', 'p.qty_step', 'p.qty_max',
                'vp.price', 'vp.price_final',
            ])
            ->from($this->db->quoteName('#__dharmashop_cart_items', 'ci'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id')
            ->join('INNER', $this->db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id')
            ->join('LEFT', $this->db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->join('LEFT', $this->db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id AND vp.price_list_id = :pl')
            ->where('ci.cart_id = :c')->order('ci.id')
            ->bind(':c', $cart->id, ParameterType::INTEGER)->bind(':pl', $cart->price_list_id, ParameterType::INTEGER);
        $lines = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $row->line_params = (array) json_decode((string) $row->line_params, true);
            // Единица, минимум и шаг — итоговые правила товара (его значение, иначе категории, иначе настройки магазина).
            $rules = $this->rules->forProduct($row);
            $row->unit = (string) $rules['unit'];
            $row->qty_min = $rules['qty_min'];
            $row->qty_step = $rules['qty_step'];
            $row->qty_max = $rules['qty_max'];
            $row->qty = (float) $row->qty;
            $row->error = $this->lineError((array) $row, $row->qty);
            $event = $this->dispatch('onDharmaShopCartItemValidate', ['cart' => $cart, 'line' => $row, 'error' => (string) ($row->error ?? '')]);
            $row->error = (string) $event->getArgument('error');
            $row->unit_price = $row->price_final !== null ? (float) $row->price_final : null;
            $row->base_price = $row->price !== null ? (float) $row->price : null;
            $row->total = $row->error === '' && $row->unit_price !== null ? round($row->unit_price * $row->qty, 2) : 0.0;
            $lines[] = $row;
        }

        return $lines;
    }

    /**
     * Итоги: сумма строк без ошибок, строки итогов плагинов (скидки, налоги, сборы) и к оплате.
     *
     * @param   object[]  $lines  результат lines()
     *
     * @return  array{count: int, qty: float, subtotal: float, adjustments: array<int, array{title: string, amount: float}>, total: float}
     */
    public function totals(object $cart, array $lines): array
    {
        $valid = array_filter($lines, static fn (object $line): bool => $line->error === '');
        $subtotal = round(array_sum(array_map(static fn (object $line): float => $line->total, $valid)), 2);
        $event = $this->dispatch('onDharmaShopCartTotals', ['cart' => $cart, 'lines' => $lines, 'subtotal' => $subtotal, 'adjustments' => []]);
        $adjustments = array_values(array_filter((array) $event->getArgument('adjustments'), static fn ($row): bool => is_array($row) && isset($row['title'], $row['amount'])));
        $total = round($subtotal + array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $adjustments)), 2);

        return [
            'count' => count($valid),
            'qty' => (float) array_sum(array_map(static fn (object $line): float => $line->qty, $valid)),
            'subtotal' => $subtotal,
            'adjustments' => $adjustments,
            'total' => max(0.0, $total),
        ];
    }

    /**
     * Быстрая сводка для значка корзины (без событий и проверок): число строк и сумма по текущим ценам.
     *
     * @return  array{count: int, qty: float, subtotal: float}
     */
    public function summary(object $cart): array
    {
        $query = $this->db->createQuery()
            ->select(['COUNT(*) AS lines_count', 'COALESCE(SUM(ci.qty), 0) AS qty_sum', 'COALESCE(SUM(ci.qty * vp.price_final), 0) AS subtotal'])
            ->from($this->db->quoteName('#__dharmashop_cart_items', 'ci'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id AND v.state = 1')
            ->join('INNER', $this->db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id AND p.state = 1')
            ->join('LEFT', $this->db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id AND vp.price_list_id = :pl')
            ->where('ci.cart_id = :c')->bind(':c', $cart->id, ParameterType::INTEGER)->bind(':pl', $cart->price_list_id, ParameterType::INTEGER);
        $row = $this->db->setQuery($query)->loadObject();

        return ['count' => (int) ($row->lines_count ?? 0), 'qty' => (float) ($row->qty_sum ?? 0), 'subtotal' => round((float) ($row->subtotal ?? 0), 2)];
    }

    /** Удаляет просроченные корзины (задача очистки). */
    public function purgeExpired(): int
    {
        $now = $this->now();
        $ids = $this->db->setQuery($this->db->createQuery()->select('id')->from($this->db->quoteName('#__dharmashop_carts'))->where('expires < :now')->bind(':now', $now))->loadColumn();

        foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {
            $list = implode(',', $chunk);
            $this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__dharmashop_cart_items') . ' WHERE cart_id IN (' . $list . ')')->execute();
            $this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__dharmashop_carts') . ' WHERE id IN (' . $list . ')')->execute();
        }

        return count($ids);
    }

    /** Переносит строки корзины гостя в корзину пользователя (одинаковые строки складываются) и удаляет корзину гостя. */
    private function merge(object $from, object $to): void
    {
        $query = $this->db->createQuery()->select($this->db->quoteName(['variant_id', 'qty', 'line_hash', 'params']))
            ->from($this->db->quoteName('#__dharmashop_cart_items'))->where('cart_id = :c')->bind(':c', $from->id, ParameterType::INTEGER);

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $this->execute(
                'INSERT INTO #__dharmashop_cart_items (cart_id, variant_id, qty, line_hash, params) VALUES (:c, :v, :q, :h, :p) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
                [':c' => [(int) $to->id, ParameterType::INTEGER], ':v' => [(int) $row->variant_id, ParameterType::INTEGER], ':q' => [(string) $row->qty], ':h' => [(string) $row->line_hash], ':p' => [$row->params, $row->params === null ? ParameterType::NULL : ParameterType::STRING]]
            );
        }

        $this->execute('DELETE FROM #__dharmashop_cart_items WHERE cart_id = :c', [':c' => [(int) $from->id, ParameterType::INTEGER]]);
        $this->execute('DELETE FROM #__dharmashop_carts WHERE id = :c', [':c' => [(int) $from->id, ParameterType::INTEGER]]);
        $this->touch((int) $to->id);
    }

    /** Данные варианта для проверок: вариант по умолчанию товара либо заданный вариант. */
    private function variantRow(int $productId, ?int $variantId): ?array
    {
        $query = $this->db->createQuery()
            ->select(['v.id', 'v.product_id', 'v.sku', 'v.state AS variant_state', 'v.stock_tracked', 'v.backorder', 'v.stock_total', 'v.in_stock',
                'p.title', 'p.catid', 'p.state AS product_state', 'p.unit', 'p.qty_min', 'p.qty_step', 'p.qty_max', 'vp.price_final'])
            ->from($this->db->quoteName('#__dharmashop_variants', 'v'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id')
            ->join('LEFT', $this->db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id AND vp.price_list_id = 1')
            ->where('v.product_id = :p')->where('v.state = 1')->bind(':p', $productId, ParameterType::INTEGER);

        if ($variantId !== null && $variantId > 0) {
            $query->where('v.id = :v')->bind(':v', $variantId, ParameterType::INTEGER);
        }

        $query->order('v.is_default DESC, v.ordering, v.id');
        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();

        return $row ?: null;
    }

    private function itemRow(int $cartId, int $itemId): ?array
    {
        $query = $this->db->createQuery()->select(['ci.id', 'ci.variant_id', 'ci.qty', 'ci.params', 'v.product_id'])
            ->from($this->db->quoteName('#__dharmashop_cart_items', 'ci'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id')
            ->where('ci.id = :i')->where('ci.cart_id = :c')->bind(':i', $itemId, ParameterType::INTEGER)->bind(':c', $cartId, ParameterType::INTEGER);

        return $this->db->setQuery($query)->loadAssoc() ?: null;
    }

    /** Товар опубликован, его основная категория и все предки доступны посетителю (как на странице товара). */
    private function purchasable(int $productId): bool
    {
        $levels = implode(',', array_map('intval', (Factory::getApplication()->getIdentity() ?? new \Joomla\CMS\User\User())->getAuthorisedViewLevels()) ?: [0]);
        $blocked = $this->db->createQuery()->select('blocked.id')->from($this->db->quoteName('#__categories', 'blocked'))
            ->where('blocked.extension = ' . $this->db->quote('com_dharmashop'))
            ->where('blocked.lft <= c.lft')->where('blocked.rgt >= c.rgt')
            ->where('(blocked.published != 1 OR blocked.access NOT IN (' . $levels . '))');
        $query = $this->db->createQuery()->select('p.id')->from($this->db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where('p.id = :p')->where('p.state = 1')->where('c.published = 1')->where('c.access IN (' . $levels . ')')
            ->where('NOT EXISTS (' . $blocked . ')')->bind(':p', $productId, ParameterType::INTEGER);

        return (bool) $this->db->setQuery($query)->loadResult();
    }

    /** Ошибка строки по текущему состоянию товара и количества или null. */
    private function lineError(array $row, float $qty): ?string
    {
        if ((int) $row['product_state'] !== 1 || (int) $row['variant_state'] !== 1) {
            return 'COM_DHARMASHOP_CART_ERROR_UNAVAILABLE';
        }

        if ($row['price_final'] === null) {
            return 'COM_DHARMASHOP_CART_ERROR_NO_PRICE';
        }

        return $this->validateQty($row, $qty, []);
    }

    /**
     * Проверка количества по правилам оформления и остатку.
     *
     * @param   array<string, mixed>  $row  данные варианта и товара (qty_min, qty_step, qty_max, catid, stock_tracked, backorder, stock_total, in_stock, price_final)
     */
    private function validateQty(array $row, float $qty, array $params): ?string
    {
        if ($row['price_final'] === null) {
            return 'COM_DHARMASHOP_CART_ERROR_NO_PRICE';
        }

        $rules = $this->rules->forProduct($row);
        $step = (float) ($rules['qty_step'] ?: 1);
        $min = (float) ($rules['qty_min'] ?: $step);
        $max = (float) $rules['qty_max'];

        if ($qty < $min - 1e-9) {
            return 'COM_DHARMASHOP_CART_ERROR_MIN';
        }

        if ($max > 0 && $qty > $max + 1e-9) {
            return 'COM_DHARMASHOP_CART_ERROR_MAX';
        }

        $steps = ($qty - $min) / $step;

        if (abs($steps - round($steps)) > 1e-6) {
            return 'COM_DHARMASHOP_CART_ERROR_STEP';
        }

        if ((int) $row['stock_tracked'] === 1 && (int) $row['backorder'] !== 1 && ((int) $row['in_stock'] === 0 || $qty > (float) $row['stock_total'] + 1e-9)) {
            return 'COM_DHARMASHOP_CART_ERROR_STOCK';
        }

        return null;
    }

    private function normalise(float $qty): float
    {
        return round($qty, 3);
    }

    private function lineHash(int $variantId, array $params): string
    {
        ksort($params);

        return md5($variantId . '|' . json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    private function fetchBy(string $column, string $value): ?object
    {
        $now = $this->now();
        $query = $this->db->createQuery()->select('*')->from($this->db->quoteName('#__dharmashop_carts'))
            ->where($this->db->quoteName($column) . ' = :v')->where('expires > :now')->bind(':v', $value)->bind(':now', $now);

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /** Продлевает срок жизни корзины. */
    private function touch(int $cartId): void
    {
        $this->execute('UPDATE #__dharmashop_carts SET modified = :m, expires = :e WHERE id = :c', [':m' => [$this->now()], ':e' => [$this->expires()], ':c' => [$cartId, ParameterType::INTEGER]]);
    }

    /** @param array<string, array{0: mixed, 1?: string}> $binds */
    private function execute(string $sql, array $binds): void
    {
        Sql::exec($this->db, $sql, $binds);
    }

    private function dispatch(string $name, array $arguments): Event
    {
        PluginHelper::importPlugin('dharmashop');
        $event = new Event($name, $arguments);
        Factory::getApplication()->getDispatcher()->dispatch($name, $event);

        return $event;
    }

    private function now(): string
    {
        return Factory::getDate()->toSql();
    }

    private function expires(): string
    {
        return Factory::getDate('+' . self::LIFETIME_DAYS . ' days')->toSql();
    }
}
