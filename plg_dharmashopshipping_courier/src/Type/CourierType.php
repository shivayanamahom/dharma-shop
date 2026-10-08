<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Courier\Type;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Dharma\Component\DharmaShop\Administrator\Shipping\Method;
use Dharma\Component\DharmaShop\Administrator\Shipping\Quote;
use Dharma\Component\DharmaShop\Administrator\Shipping\TypeInterface;
use Dharma\Plugin\DharmaShopShipping\Courier\Geo\Distance;
use Dharma\Plugin\DharmaShopShipping\Courier\Geo\Geocoder;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Курьер: доставка до двери. Цена — фиксированная либо по расстоянию: базовая цена + цена за километр от ближайшего склада
 * до адреса покупателя (адрес определяет серверный геокодер Яндекса; расстояние — по прямой с дорожным коэффициентом).
 * «Бесплатно от суммы заказа» и пределы суммы заказа действуют в обоих режимах. Поля покупателя — конструктор; у поля можно задать
 * роль в расчёте (город, адрес): по ним определяется точка доставки и работают подсказки адреса.
 */
final class CourierType implements TypeInterface
{
    /** Подмена серверного геокодера для автоматических проверок (в работе всегда null): функция принимает ключ и возвращает объект с методом search(). */
    public static ?\Closure $geocoderFactory = null;

    private const FIELD_TYPES = ['text', 'textarea', 'tel', 'select'];

    private const ROLES = ['none', 'city', 'address'];

    private const MARKER_KINDS = ['pin', 'dot', 'circle', 'label'];

    private const COLORS = ['blue', 'red', 'green', 'darkGreen', 'darkBlue', 'lightBlue', 'orange', 'darkOrange', 'violet', 'pink', 'yellow', 'brown', 'black', 'gray', 'night'];

    private const RATIOS = ['21x9', '16x9', '4x3', '1x1'];

    public function id(): string
    {
        return 'courier';
    }

    public function label(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_COURIER_TYPE';
    }

    public function description(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_COURIER_DESC';
    }

    public function settingsPath(): string
    {
        return dirname(__DIR__, 2) . '/forms/settings.xml';
    }

    // ---------------------------------------------------------------- настройки

    public function normalizeSettings(array $settings): array
    {
        $number = static function (mixed $value, string $label, float $max = 1000000000.0): float {
            $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

            if ($text === '') {
                return 0.0;
            }

            if (!is_numeric($text) || (float) $text < 0 || (float) $text > $max) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_' . $label);
            }

            return round((float) $text, 2);
        };

        $mode = (string) ($settings['price_mode'] ?? 'fixed') === 'distance' ? 'distance' : 'fixed';
        $factor = $number($settings['km_road_factor'] ?? 1.3, 'ROAD_FACTOR', 5.0);
        $key = trim((string) ($settings['yandex_apikey'] ?? ''));

        if ($key !== '' && !preg_match('/^[A-Za-z0-9\-]{8,100}$/D', $key)) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_APIKEY');
        }

        $normalized = [
            'term' => mb_substr(trim(strip_tags((string) ($settings['term'] ?? ''))), 0, 100),
            'note' => mb_substr(trim(strip_tags((string) ($settings['note'] ?? ''))), 0, 500),
            'fields' => $this->normalizeFields((array) ($settings['fields'] ?? []), $mode),
            'price_mode' => $mode,
            'price' => $number($settings['price'] ?? 0, 'PRICE'),
            'km_base_price' => $number($settings['km_base_price'] ?? 0, 'KM_BASE'),
            'km_included' => $number($settings['km_included'] ?? 0, 'KM_INCLUDED', 10000.0),
            'km_price' => $number($settings['km_price'] ?? 0, 'KM_PRICE'),
            'km_max_price' => $number($settings['km_max_price'] ?? 0, 'KM_MAX_PRICE'),
            'km_max_distance' => $number($settings['km_max_distance'] ?? 0, 'KM_MAX_DISTANCE', 100000.0),
            'km_road_factor' => $factor > 0 ? $factor : 1.3,
            'km_round' => in_array((string) ($settings['km_round'] ?? ''), ['0', '1', '5', '10', '50', '100'], true) ? (string) $settings['km_round'] : '10',
            'free_from' => $number($settings['free_from'] ?? 0, 'FREE_FROM'),
            'min_total' => $number($settings['min_total'] ?? 0, 'MIN_TOTAL'),
            'max_total' => $number($settings['max_total'] ?? 0, 'MAX_TOTAL'),
            'warehouses' => $this->normalizeWarehouses((array) ($settings['warehouses'] ?? [])),
            'yandex_apikey' => $key,
            'suggest' => (int) ($settings['suggest'] ?? 1) === 1 ? '1' : '0',
            'map' => in_array((string) ($settings['map'] ?? ''), ['none', 'yandex'], true) ? (string) $settings['map'] : 'none',
            'marker_kind' => in_array((string) ($settings['marker_kind'] ?? ''), self::MARKER_KINDS, true) ? (string) $settings['marker_kind'] : 'pin',
            'marker_color' => in_array((string) ($settings['marker_color'] ?? ''), self::COLORS, true) ? (string) $settings['marker_color'] : 'blue',
            'marker_nearest_color' => in_array((string) ($settings['marker_nearest_color'] ?? ''), self::COLORS, true) ? (string) $settings['marker_nearest_color'] : 'green',
            'marker_address_color' => in_array((string) ($settings['marker_address_color'] ?? ''), self::COLORS, true) ? (string) $settings['marker_address_color'] : 'red',
            'zoom' => (string) min(19, max(0, (int) ($settings['zoom'] ?? 0))),
            'map_ratio' => in_array((string) ($settings['map_ratio'] ?? ''), self::RATIOS, true) ? (string) $settings['map_ratio'] : '16x9',
        ];

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_RANGE');
        }

        if ($mode === 'distance') {
            // Расчёт по расстоянию: нужен адрес (поле с ролью «Адрес»), хотя бы один склад с координатами и ключ геокодера.
            if (!array_filter($normalized['fields'], static fn (array $field): bool => $field['role'] === 'address')) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_ROLE_ADDRESS');
            }

            if (!$normalized['warehouses']) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_WAREHOUSES');
            }

            if ($key === '') {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_KEY_NEEDED');
            }
        }

        return $normalized;
    }

    /** Поля покупателя: ключ из подписи (латиница, уникальный); роль в расчёте — не более одного города и одного адреса; роль делает поле обязательным. */
    private function normalizeFields(array $rows, string $mode): array
    {
        $fields = [];
        $used = [];
        $roles = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $label = mb_substr(trim(strip_tags((string) ($row['label'] ?? ''))), 0, 100);

            if ($label === '') {
                continue;
            }

            $type = in_array((string) ($row['type'] ?? ''), self::FIELD_TYPES, true) ? (string) $row['type'] : 'text';
            $role = in_array((string) ($row['role'] ?? ''), self::ROLES, true) ? (string) $row['role'] : 'none';

            if ($role !== 'none') {
                if (isset($roles[$role])) {
                    throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_ROLE_TWICE');
                }

                if ($type === 'select' || $type === 'tel') {
                    throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_ROLE_TYPE');
                }

                $roles[$role] = true;
            }

            $key = substr(str_replace('-', '_', ApplicationHelper::stringURLSafe($label)) ?: 'field', 0, 40);
            $base = $key;

            for ($n = 2; isset($used[$key]); $n++) {
                $key = $base . '_' . $n;
            }

            $used[$key] = true;
            $options = [];

            if ($type === 'select') {
                foreach (preg_split('/\R/u', (string) ($row['options'] ?? '')) ?: [] as $option) {
                    $option = mb_substr(trim(strip_tags($option)), 0, 100);

                    if ($option !== '') {
                        $options[] = $option;
                    }
                }

                $options = array_slice(array_values(array_unique($options)), 0, 50);

                if (!$options) {
                    throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_OPTIONS');
                }
            }

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'role' => $role,
                'required' => $role !== 'none' && $mode === 'distance' ? 1 : ((int) ($row['required'] ?? 0) === 1 ? 1 : 0),
                'width' => (string) ($row['width'] ?? 'full') === 'half' ? 'half' : 'full',
                'placeholder' => mb_substr(trim(strip_tags((string) ($row['placeholder'] ?? ''))), 0, 100),
                // Строкой (по варианту в строке), как вводится в форме: массив в поле формы ломает вывод textarea.
                'options' => implode("\n", $options),
            ];
        }

        return $fields;
    }

    /** Склады отправки: название и координаты в формате Яндекса обязательны, адрес — справочно. */
    private function normalizeWarehouses(array $rows): array
    {
        $warehouses = [];
        $used = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $name = mb_substr(trim(strip_tags((string) ($row['name'] ?? ''))), 0, 150);
            $coordsRaw = trim((string) ($row['coords'] ?? ''));

            if ($name === '' && $coordsRaw === '') {
                continue;
            }

            $coords = self::parseCoords($coordsRaw);

            if ($name === '' || $coords === null) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_WAREHOUSE');
            }

            $key = substr(str_replace('-', '_', ApplicationHelper::stringURLSafe($name)) ?: 'warehouse', 0, 40);
            $base = $key;

            for ($n = 2; isset($used[$key]); $n++) {
                $key = $base . '_' . $n;
            }

            $used[$key] = true;
            $warehouses[] = ['key' => $key, 'name' => $name, 'address' => mb_substr(trim(strip_tags((string) ($row['address'] ?? ''))), 0, 500), 'coords' => $coords[0] . ', ' . $coords[1]];
        }

        return $warehouses;
    }

    /**
     * Координаты в виде Яндекса «[55.753994, 37.622093]» (широта, долгота); допустимы запись без скобок, разделители запятая/точка с запятой/пробел
     * и десятичная запятая. Возвращает [широта, долгота] строками или null.
     *
     * @return  array{0: string, 1: string}|null
     */
    public static function parseCoords(string $value): ?array
    {
        $value = trim(str_replace(['[', ']', '(', ')'], ' ', $value));

        if (!preg_match('/^(-?\d+(?:[.,]\d+)?)\s*[;,\s]\s*(-?\d+(?:[.,]\d+)?)$/D', $value, $m)) {
            return null;
        }

        $lat = (float) str_replace(',', '.', $m[1]);
        $lon = (float) str_replace(',', '.', $m[2]);

        if (abs($lat) > 90 || abs($lon) > 180) {
            return null;
        }

        return [rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.'), rtrim(rtrim(number_format($lon, 6, '.', ''), '0'), '.')];
    }

    // ---------------------------------------------------------------- расчёт

    public function quote(Method $method, Context $context): ?Quote
    {
        $p = $method->params;
        $min = (float) ($p['min_total'] ?? 0);
        $max = (float) ($p['max_total'] ?? 0);

        if (($min > 0 && $context->total < $min) || ($max > 0 && $context->total > $max)) {
            return null;
        }

        $term = (string) ($p['term'] ?? '');
        $note = (string) ($p['note'] ?? '');
        $freeFrom = (float) ($p['free_from'] ?? 0);
        $free = $freeFrom > 0 && $context->total >= $freeFrom;

        if ($this->isDistance($method)) {
            if (!$this->warehousesOf($method) || $this->apiKey($method) === '') {
                return null;
            }

            // Цена зависит от адреса: до его ввода — «рассчитывается по адресу» (кроме заказов, уже попавших под бесплатную доставку).
            return $free ? new Quote(0.0, $term, $note, true) : new Quote(0.0, $term, $note, false, true);
        }

        $price = (float) ($p['price'] ?? 0);
        $free = $free || $price <= 0;

        return new Quote($free ? 0.0 : round($price, 2), $term, $note, $free);
    }

    public function describe(Method $method, Quote $quote): array
    {
        return $quote->note !== '' ? [['label' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ROW_NOTE'), 'value' => $quote->note]] : [];
    }

    public function validateInput(Method $method, Context $context, array $input): array
    {
        $data = [];
        $rows = [];
        $errors = [];
        $values = [];

        foreach ($this->fieldsOf($method) as $field) {
            $key = (string) $field['key'];
            $raw = trim((string) preg_replace('/[ \t]+/u', ' ', strip_tags((string) ($input[$key] ?? ''))));
            $raw = (string) preg_replace('/\R{3,}/u', "\n\n", $raw);

            if ($raw === '') {
                if ((int) $field['required'] === 1) {
                    $errors[$key] = 'PLG_DHARMASHOPSHIPPING_COURIER_FIELD_REQUIRED_ERROR';
                }

                continue;
            }

            $limit = $field['type'] === 'textarea' ? 1000 : 255;

            if (mb_strlen($raw) > $limit) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_COURIER_FIELD_LONG';
            } elseif ($field['type'] === 'select' && !in_array($raw, (array) $field['options'], true)) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_COURIER_FIELD_INVALID';
            } elseif ($field['type'] === 'tel' && !preg_match('/^[0-9+()\-\s.]{5,25}$/D', $raw)) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_COURIER_FIELD_INVALID';
            } else {
                $data[$key] = $raw;
                $values[(string) $field['role']] = $raw;
                $rows[] = ['label' => (string) $field['label'], 'value' => $raw];
            }
        }

        $result = ['data' => $data, 'rows' => $rows, 'errors' => $errors];

        if (!$errors && $this->isDistance($method)) {
            $addressKey = (string) ($this->roleKey($method, 'address') ?? '');

            try {
                $calc = $this->calculate($method, $context, (string) ($values['city'] ?? ''), (string) ($values['address'] ?? ''));
                $result['quote'] = $calc['quote'];
                $result['data']['distance_km'] = $calc['distance'];
                $result['data']['warehouse'] = $calc['warehouse']['key'];
                $result['data']['to'] = $calc['to'];
                $result['rows'][] = ['label' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ROW_DISTANCE'), 'value' => Text::sprintf('PLG_DHARMASHOPSHIPPING_COURIER_DISTANCE_VALUE', $this->km($calc['distance']))];
                $result['rows'][] = ['label' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ROW_WAREHOUSE'), 'value' => $calc['warehouse']['name']];
            } catch (\DomainException $error) {
                $result['errors'][$addressKey !== '' ? $addressKey : 'address'] = $error->getMessage();
            }
        }

        return $result;
    }

    /**
     * Расчёт по расстоянию: адрес → координаты (серверный геокодер) → ближайший склад → расстояние → цена.
     *
     * @return  array{quote: Quote, distance: float, warehouse: array<string, mixed>, to: array{0: float, 1: float}}
     *
     * @throws  \DomainException  адрес не распознан, слишком далеко, сервис недоступен — сообщение (языковой ключ) показывается покупателю
     */
    private function calculate(Method $method, Context $context, string $city, string $address): array
    {
        $p = $method->params;
        $warehouses = $this->warehousesOf($method);
        $key = $this->apiKey($method);

        if (!$warehouses || $key === '') {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_NOT_CONFIGURED');
        }

        $query = trim($city !== '' ? $city . ', ' . $address : $address);
        $found = null;

        try {
            foreach ($this->geocoder($key)->search($query, 3, $warehouses[0]['coords']) as $item) {
                if (in_array($item['precision'], Geocoder::GOOD_PRECISION, true)) {
                    $found = $item;
                    break;
                }
            }
        } catch (\RuntimeException) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_GEOCODER');
        }

        if ($found === null) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_ADDRESS');
        }

        $nearest = Distance::nearest($warehouses, $found['coords'][0], $found['coords'][1]);
        $distance = round($nearest['km'] * (float) ($p['km_road_factor'] ?? 1.3), 1);
        $maxDistance = (float) ($p['km_max_distance'] ?? 0);

        if ($maxDistance > 0 && $distance > $maxDistance) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_TOO_FAR');
        }

        $freeFrom = (float) ($p['free_from'] ?? 0);
        $free = $freeFrom > 0 && $context->total >= $freeFrom;
        $price = (float) ($p['km_base_price'] ?? 0) + max(0.0, $distance - (float) ($p['km_included'] ?? 0)) * (float) ($p['km_price'] ?? 0);
        $cap = (float) ($p['km_max_price'] ?? 0);
        $price = $cap > 0 ? min($price, $cap) : $price;
        $step = (float) ($p['km_round'] ?? 10);
        $price = $step > 0 ? ceil($price / $step - 1e-9) * $step : round($price, 2);
        $free = $free || $price <= 0;

        return [
            'quote' => new Quote($free ? 0.0 : round($price, 2), (string) ($p['term'] ?? ''), (string) ($p['note'] ?? ''), $free),
            'distance' => $distance,
            'warehouse' => $warehouses[$nearest['index']],
            'to' => $found['coords'],
        ];
    }

    // ---------------------------------------------------------------- динамические запросы

    public function ajax(Method $method, Context $context, string $action, array $input): array
    {
        if (!$this->isDistance($method) && $action !== 'suggest') {
            return ['ok' => false, 'error' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_NOT_CONFIGURED')];
        }

        $key = $this->apiKey($method);

        if ($key === '') {
            return ['ok' => false, 'error' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_NOT_CONFIGURED')];
        }

        if ($action === 'suggest') {
            if ((string) ($method->params['suggest'] ?? '1') !== '1') {
                return ['ok' => true, 'items' => []];
            }

            $text = trim(strip_tags((string) ($input['text'] ?? '')));
            $city = trim(strip_tags((string) ($input['city'] ?? '')));

            if (mb_strlen($text) < 3 || mb_strlen($text) > 200) {
                return ['ok' => true, 'items' => []];
            }

            try {
                $warehouses = $this->warehousesOf($method);
                $items = [];

                foreach ($this->geocoder($key)->search($city !== '' ? $city . ', ' . $text : $text, 6, $warehouses[0]['coords'] ?? null) as $item) {
                    $items[] = ['label' => $item['text'], 'address' => $item['address'], 'city' => $item['city']];
                }

                return ['ok' => true, 'items' => $items];
            } catch (\RuntimeException) {
                return ['ok' => false, 'error' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_GEOCODER')];
            }
        }

        if ($action === 'quote') {
            $city = trim(strip_tags((string) ($input['city'] ?? '')));
            $address = trim(strip_tags((string) ($input['address'] ?? '')));

            if ($address === '' || mb_strlen($address) > 255 || mb_strlen($city) > 255) {
                return ['ok' => false, 'error' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ERROR_ADDRESS')];
            }

            try {
                $calc = $this->calculate($method, $context, $city, $address);
            } catch (\DomainException $error) {
                return ['ok' => false, 'error' => Text::_($error->getMessage())];
            }

            $money = Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->money();
            $quote = $calc['quote'];

            return [
                'ok' => true,
                'price' => $quote->price,
                'free' => $quote->free,
                'price_text' => $quote->free ? Text::_('COM_DHARMASHOP_SHIPPING_FREE') : $money->format((string) $quote->price),
                'total_text' => $money->price((string) round($context->total + $quote->price, 2)),
                'distance_km' => $calc['distance'],
                'warehouse' => ['key' => $calc['warehouse']['key'], 'name' => $calc['warehouse']['name'], 'address' => $calc['warehouse']['address']],
                'to' => $calc['to'],
                'rows' => [
                    ['label' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ROW_DISTANCE'), 'value' => Text::sprintf('PLG_DHARMASHOPSHIPPING_COURIER_DISTANCE_VALUE', $this->km($calc['distance']))],
                    ['label' => Text::_('PLG_DHARMASHOPSHIPPING_COURIER_ROW_WAREHOUSE'), 'value' => $calc['warehouse']['name']],
                ],
            ];
        }

        return ['ok' => false, 'error' => 'Unknown action'];
    }

    // ---------------------------------------------------------------- вывод

    public function render(array $displayData): string
    {
        $method = $displayData['method'];
        $p = $method->params;
        $path = PluginHelper::getLayoutPath('dharmashopshipping', 'courier', 'default');
        $displayData['fields'] = $this->fieldsOf($method);
        $displayData['distance_mode'] = $this->isDistance($method);
        $displayData['suggest'] = $this->isDistance($method) && (string) ($p['suggest'] ?? '1') === '1' && $this->apiKey($method) !== '';
        $displayData['map'] = $this->mapDisplay($method);

        if ($displayData['distance_mode'] || $displayData['map']['mode'] !== 'none') {
            $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
            $wa->getRegistry()->addRegistryFile('media/plg_dharmashopshipping_courier/joomla.asset.json');
            $wa->useScript('plg_dharmashopshipping_courier.courier');
        }

        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }

    /** @return array{mode: string, ratio: string, config: string} */
    private function mapDisplay(Method $method): array
    {
        $p = $method->params;
        $ratio = in_array((string) ($p['map_ratio'] ?? ''), self::RATIOS, true) ? (string) $p['map_ratio'] : '16x9';
        $warehouses = $this->warehousesOf($method);

        if ((string) ($p['map'] ?? 'none') !== 'yandex' || $this->apiKey($method) === '' || !$warehouses || !$this->isDistance($method)) {
            return ['mode' => 'none', 'ratio' => $ratio, 'config' => ''];
        }

        $config = [
            'apikey' => $this->apiKey($method),
            'zoom' => (int) ($p['zoom'] ?? 0),
            'markerKind' => in_array((string) ($p['marker_kind'] ?? ''), self::MARKER_KINDS, true) ? (string) $p['marker_kind'] : 'pin',
            'warehouseColor' => in_array((string) ($p['marker_color'] ?? ''), self::COLORS, true) ? (string) $p['marker_color'] : 'blue',
            'nearestColor' => in_array((string) ($p['marker_nearest_color'] ?? ''), self::COLORS, true) ? (string) $p['marker_nearest_color'] : 'green',
            'addressColor' => in_array((string) ($p['marker_address_color'] ?? ''), self::COLORS, true) ? (string) $p['marker_address_color'] : 'red',
            'warehouses' => array_map(static fn (array $w): array => ['key' => $w['key'], 'name' => $w['name'], 'address' => $w['address'], 'coords' => $w['coords']], $warehouses),
        ];

        return ['mode' => 'js', 'ratio' => $ratio, 'config' => (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)];
    }

    // ---------------------------------------------------------------- вспомогательное

    private function geocoder(string $key): object
    {
        return self::$geocoderFactory !== null ? (self::$geocoderFactory)($key) : new Geocoder($key);
    }

    private function isDistance(Method $method): bool
    {
        return (string) ($method->params['price_mode'] ?? 'fixed') === 'distance';
    }

    private function apiKey(Method $method): string
    {
        return trim((string) ($method->params['yandex_apikey'] ?? ''));
    }

    /** @return array<int, array{key: string, name: string, address: string, coords: array{0: float, 1: float}}> */
    private function warehousesOf(Method $method): array
    {
        $list = [];

        foreach ((array) ($method->params['warehouses'] ?? []) as $row) {
            $coords = is_array($row) ? self::parseCoords((string) ($row['coords'] ?? '')) : null;

            if ($coords !== null) {
                $list[] = ['key' => (string) $row['key'], 'name' => (string) $row['name'], 'address' => (string) ($row['address'] ?? ''), 'coords' => [(float) $coords[0], (float) $coords[1]]];
            }
        }

        return $list;
    }

    private function roleKey(Method $method, string $role): ?string
    {
        foreach ($this->fieldsOf($method) as $field) {
            if ($field['role'] === $role) {
                return (string) $field['key'];
            }
        }

        return null;
    }

    private function km(float $km): string
    {
        return rtrim(rtrim(number_format($km, 1, ',', ' '), '0'), ',');
    }

    /** Определения полей способа; если настройки ещё не сохранялись — город и адрес с ролями. */
    private function fieldsOf(Method $method): array
    {
        if (isset($method->params['fields']) && is_array($method->params['fields'])) {
            return array_map(static function (array $field): array {
                $options = $field['options'] ?? [];
                $field['options'] = array_values(array_filter(is_array($options) ? $options : (preg_split('/\R/u', (string) $options) ?: []), static fn ($option): bool => trim((string) $option) !== ''));
                $field['role'] = (string) ($field['role'] ?? 'none');

                return $field;
            }, array_values(array_filter($method->params['fields'], 'is_array')));
        }

        $line = static fn (string $key, string $label, string $type, string $role, int $required, string $width): array
            => ['key' => $key, 'label' => $label, 'type' => $type, 'role' => $role, 'required' => $required, 'width' => $width, 'placeholder' => '', 'options' => []];

        return [
            $line('gorod', Text::_('PLG_DHARMASHOPSHIPPING_COURIER_DEFAULT_CITY'), 'text', 'city', 1, 'half'),
            $line('ulitsa_i_dom', Text::_('PLG_DHARMASHOPSHIPPING_COURIER_DEFAULT_STREET'), 'text', 'address', 1, 'half'),
        ];
    }
}
