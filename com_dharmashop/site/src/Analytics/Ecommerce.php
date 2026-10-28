<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Analytics;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Электронная коммерция для счётчиков (C98): просмотр товара, добавление и удаление из корзины, покупка. Формат — `dataLayer`
 * Яндекс Метрики (читается и Google Tag Manager / GA4). Включается общей настройкой `analytics_ecommerce` (по умолчанию выключена):
 * тогда ядро собирает данные из уже загруженных записей и передаёт их скрипту `ecommerce.js`; выключенная — ни запросов, ни
 * скрипта, ни данных в странице. Другие счётчики (VK, свой код) слушают событие браузера `dharmashop:ecommerce`.
 *
 * Товар: `id` — номер товара (как `offer id` в фидах), `name`, `price` (число), `quantity`, `category`, `brand`, `variant`.
 */
final class Ecommerce
{
    /** Покупка отправляется один раз за заказ; через столько секунд после оформления — уже нет. */
    private const PURCHASE_TTL = 7200;

    public static function enabled(Registry $settings): bool
    {
        return (int) $settings->get('analytics_ecommerce', 0) === 1;
    }

    public static function currency(Registry $settings): string
    {
        return strtoupper(trim((string) $settings->get('currency_code', 'RUB'))) ?: 'RUB';
    }

    /** Имя массива данных на странице: только допустимый идентификатор JavaScript, иначе `dataLayer`. */
    public static function container(Registry $settings): string
    {
        $name = trim((string) $settings->get('analytics_container', 'dataLayer'));

        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]{0,63}$/', $name) === 1 ? $name : 'dataLayer';
    }

    /** Товар в нейтральном виде; пустые необязательные поля не передаются. */
    public static function product(int $id, string $name, ?float $price, float $quantity = 1.0, string $category = '', string $brand = '', string $variant = ''): array
    {
        $row = ['id' => (string) $id, 'name' => trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'))];

        if ($price !== null && $price >= 0) {
            $row['price'] = round($price, 2);
        }

        if ($quantity > 0) {
            $row['quantity'] = $quantity == (int) $quantity ? (int) $quantity : round($quantity, 3);
        }

        foreach (['category' => $category, 'brand' => $brand, 'variant' => $variant] as $key => $value) {
            $value = trim($value);

            if ($value !== '') {
                $row[$key] = $value;
            }
        }

        return $row;
    }

    /** Подключает `ecommerce.js` и его настройки; идемпотентно. Вызывается там же, где подключается скрипт корзины. */
    public static function assets(HtmlDocument $document, Registry $settings): void
    {
        if (!self::enabled($settings)) {
            return;
        }

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.ecommerce');
        $document->addScriptOptions('com_dharmashop.ecommerce', self::options($document, $settings));
    }

    /**
     * Событие страницы (`detail`, `purchase`): записывается в настройки скрипта и отправляется им при загрузке страницы.
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed>             $actionField для `purchase`: `id` (номер заказа), `revenue`
     */
    public static function emit(string $action, HtmlDocument $document, Registry $settings, array $products, array $actionField = []): void
    {
        if (!self::enabled($settings) || $products === []) {
            return;
        }

        self::assets($document, $settings);
        $options = self::options($document, $settings);
        $event = ['action' => $action, 'products' => array_values($products)];

        if ($actionField !== []) {
            $event['actionField'] = $actionField;
        }

        $options['events'][] = $event;
        // merge=false: иначе список событий склеивается по индексам и теряет записи.
        $document->addScriptOptions('com_dharmashop.ecommerce', $options, false);
    }

    /**
     * Покупка по оформленному заказу — один раз: первая страница магазина, показанная после оформления (спасибо, оплата,
     * страница заказа после возврата со шлюза), отправляет событие и ставит отметку в данных оформления сессии.
     */
    public static function purchase(object $order, HtmlDocument $document, Registry $settings): void
    {
        if (!self::enabled($settings)) {
            return;
        }

        $app = Factory::getApplication();
        $receipt = (array) $app->getUserState('com_dharmashop.checkout.receipt', []);

        if ((int) ($receipt['id'] ?? 0) !== (int) $order->id || !empty($receipt['ecommerce_sent']) || (int) ($receipt['time'] ?? 0) < time() - self::PURCHASE_TTL) {
            return;
        }

        // Отметка раньше отправки: сбой не должен приводить к повторной покупке при обновлении страницы.
        $receipt['ecommerce_sent'] = 1;
        $app->setUserState('com_dharmashop.checkout.receipt', $receipt);

        $items = (array) ($order->items ?? []);
        $categories = self::categoryTitles(array_map(static fn (object $item): int => (int) ($item->product_id ?? 0), $items));
        $products = [];

        foreach ($items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $title = (string) ($item->title ?? '');

            if ($productId < 1 && $title === '') {
                continue;
            }

            $products[] = self::product($productId, $title, $item->price !== null ? (float) $item->price : null, (float) $item->qty, $categories[$productId] ?? '');
        }

        self::emit('purchase', $document, $settings, $products, ['id' => (string) $order->number, 'revenue' => round((float) $order->total, 2)]);
    }

    /**
     * Состояние корзины для сравнения до и после действия: позиция → товар с количеством. Один запрос по ключу корзины.
     *
     * @return array<int, array<string, mixed>> номер строки корзины → ['product' => товар, 'qty' => количество]
     */
    public static function cartSnapshot(?object $cart): array
    {
        if ($cart === null) {
            return [];
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $cartId = (int) $cart->id;
        $priceList = (int) $cart->price_list_id;
        $query = $db->createQuery()
            ->select(['ci.id', 'ci.qty', 'v.product_id', 'v.title AS variant_title', 'p.title', 'c.title AS category_title', 'vp.price_final'])
            ->from($db->quoteName('#__dharmashop_cart_items', 'ci'))
            ->join('INNER', $db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id')
            ->join('INNER', $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id')
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->join('LEFT', $db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id AND vp.price_list_id = :pl')
            ->where('ci.cart_id = :c')
            ->bind(':c', $cartId, \Joomla\Database\ParameterType::INTEGER)->bind(':pl', $priceList, \Joomla\Database\ParameterType::INTEGER);
        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $rows[(int) $row->id] = [
                'qty' => (float) $row->qty,
                'product' => self::product((int) $row->product_id, (string) $row->title, $row->price_final !== null ? (float) $row->price_final : null, 0.0,
                    (string) ($row->category_title ?? ''), '', (string) ($row->variant_title ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Что изменилось между двумя снимками: прибавление количества — `add`, уменьшение и удаление — `remove`; в количестве
     * передаётся только изменение. Возвращает данные для ответа корзины или null, если количества не менялись.
     *
     * @param  array<int, array<string, mixed>> $before
     * @param  array<int, array<string, mixed>> $after
     * @return array{uid: string, currency: string, events: list<array<string, mixed>>}|null
     */
    public static function cartChanges(array $before, array $after, Registry $settings): ?array
    {
        $added = [];
        $removed = [];

        foreach ($before + $after as $itemId => $_) {
            $delta = round((float) ($after[$itemId]['qty'] ?? 0) - (float) ($before[$itemId]['qty'] ?? 0), 3);

            if ($delta == 0.0) {
                continue;
            }

            $product = ($after[$itemId] ?? $before[$itemId])['product'];
            $product['quantity'] = abs($delta) == (int) abs($delta) ? (int) abs($delta) : abs($delta);

            if ($delta > 0) {
                $added[] = $product;
            } else {
                $removed[] = $product;
            }
        }

        $events = [];

        foreach (['add' => $added, 'remove' => $removed] as $action => $products) {
            if ($products !== []) {
                $events[] = ['action' => $action, 'products' => $products];
            }
        }

        // uid защищает от повторной отправки одного и того же ответа несколькими обработчиками на странице.
        return $events === [] ? null : ['uid' => bin2hex(random_bytes(6)), 'currency' => self::currency($settings), 'events' => $events];
    }

    /**
     * Название основной категории по номерам товаров одним запросом (по первичному ключу).
     *
     * @param  int[] $productIds
     * @return array<int, string>
     */
    public static function categoryTitles(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));

        if ($productIds === []) {
            return [];
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->createQuery()->select([$db->quoteName('p.id'), $db->quoteName('c.title')])
            ->from($db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where($db->quoteName('p.id') . ' IN (' . implode(',', $productIds) . ')');
        $titles = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $titles[(int) $row->id] = (string) $row->title;
        }

        return $titles;
    }

    /** Текущие настройки скрипта на странице (с накопленным списком событий). */
    private static function options(HtmlDocument $document, Registry $settings): array
    {
        $existing = (array) $document->getScriptOptions('com_dharmashop.ecommerce');

        return array_merge(['container' => self::container($settings), 'currency' => self::currency($settings), 'events' => []], $existing);
    }
}
