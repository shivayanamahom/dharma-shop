<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Pickup\Type;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Dharma\Component\DharmaShop\Administrator\Shipping\Method;
use Dharma\Component\DharmaShop\Administrator\Shipping\Quote;
use Dharma\Component\DharmaShop\Administrator\Shipping\TypeInterface;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Самовывоз: несколько пунктов выдачи (складов) в одном способе — повторяемая форма; у пункта адрес, контакты, часы работы,
 * дополнительные сведения и карта. Покупатель выбирает пункт; цена и ограничения по сумме общие для способа.
 */
final class PickupType implements TypeInterface
{
    private const MAPS = ['none', 'yandex'];

    private const MARKER_KINDS = ['pin', 'dot', 'circle', 'label'];

    private const COLORS = ['blue', 'red', 'green', 'darkGreen', 'darkBlue', 'lightBlue', 'orange', 'darkOrange', 'violet', 'pink', 'yellow', 'brown', 'black', 'gray', 'night'];

    private const RATIOS = ['21x9', '16x9', '4x3', '1x1'];

    public function id(): string
    {
        return 'pickup';
    }

    public function label(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_PICKUP_TYPE';
    }

    public function description(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_PICKUP_DESC';
    }

    public function settingsPath(): string
    {
        return dirname(__DIR__, 2) . '/forms/settings.xml';
    }

    public function normalizeSettings(array $settings): array
    {
        $money = static function (mixed $value, string $label): float {
            $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

            if ($text === '') {
                return 0.0;
            }

            if (!is_numeric($text) || (float) $text < 0 || (float) $text > 1000000000) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_' . $label);
            }

            return round((float) $text, 2);
        };

        $normalized = [
            'term' => mb_substr(trim(strip_tags((string) ($settings['term'] ?? ''))), 0, 100),
            'price' => $money($settings['price'] ?? 0, 'PRICE'),
            'free_from' => $money($settings['free_from'] ?? 0, 'FREE_FROM'),
            'min_total' => $money($settings['min_total'] ?? 0, 'MIN_TOTAL'),
            'max_total' => $money($settings['max_total'] ?? 0, 'MAX_TOTAL'),
            'map' => in_array((string) ($settings['map'] ?? ''), self::MAPS, true) ? (string) $settings['map'] : 'none',
            'zoom' => (string) min(19, max(0, (int) ($settings['zoom'] ?? 0))),
            'zoom_selected' => (string) min(19, max(0, (int) ($settings['zoom_selected'] ?? 15))),
            'map_ratio' => in_array((string) ($settings['map_ratio'] ?? ''), self::RATIOS, true) ? (string) $settings['map_ratio'] : '16x9',
            'yandex_apikey' => trim((string) ($settings['yandex_apikey'] ?? '')),
            'marker_kind' => in_array((string) ($settings['marker_kind'] ?? ''), self::MARKER_KINDS, true) ? (string) $settings['marker_kind'] : 'pin',
            'marker_address' => self::markerAddressMode($settings['marker_address'] ?? null),
            'marker_color' => in_array((string) ($settings['marker_color'] ?? ''), self::COLORS, true) ? (string) $settings['marker_color'] : 'blue',
            'marker_selected_color' => in_array((string) ($settings['marker_selected_color'] ?? ''), self::COLORS, true) ? (string) $settings['marker_selected_color'] : 'red',
            'center' => '',
            'points' => $this->normalizePoints((array) ($settings['points'] ?? [])),
        ];

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_RANGE');
        }

        if (!$normalized['points']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_POINTS');
        }

        // Ключ API Яндекса — публичный ключ браузера (виден в коде страницы): только буквы, цифры и дефисы.
        if ($normalized['yandex_apikey'] !== '' && !preg_match('/^[A-Za-z0-9\-]{8,100}$/D', $normalized['yandex_apikey'])) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_APIKEY');
        }

        // Центр карты (необязателен) — в том же формате, что координаты складов; пусто — по выбранному пункту или по всем пунктам.
        $centerRaw = trim((string) ($settings['center'] ?? ''));

        if ($centerRaw !== '') {
            $center = self::parseCoords($centerRaw);

            if ($center === null) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_COORDS');
            }

            $normalized['center'] = $center[0] . ', ' . $center[1];
        }

        // Карта общая для способа: нужен хотя бы один пункт с координатами.
        if ($normalized['map'] !== 'none' && !array_filter($normalized['points'], static fn (array $point): bool => $point['coords'] !== '')) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_COORDS_NEEDED');
        }

        return $normalized;
    }

    /** Пункты выдачи (склады): ключ из названия (латиница, уникальный), обязательны название и адрес, координаты — числами. */
    private function normalizePoints(array $rows): array
    {
        $points = [];
        $used = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $text = static fn (string $key, int $limit): string => mb_substr(trim(strip_tags((string) ($row[$key] ?? ''))), 0, $limit);
            $name = $text('name', 150);
            $address = $text('address', 500);

            if ($name === '' && $address === '') {
                continue;
            }

            if ($name === '' || $address === '') {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_POINT');
            }

            $email = $text('email', 190);

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_EMAIL');
            }

            $coords = '';
            $coordsRaw = trim((string) ($row['coords'] ?? ''));

            if ($coordsRaw !== '') {
                $parsed = self::parseCoords($coordsRaw);

                if ($parsed === null) {
                    throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_COORDS');
                }

                $coords = $parsed[0] . ', ' . $parsed[1];
            }

            $key = substr(str_replace('-', '_', ApplicationHelper::stringURLSafe($name)) ?: 'point', 0, 40);
            $base = $key;

            for ($n = 2; isset($used[$key]); $n++) {
                $key = $base . '_' . $n;
            }

            $used[$key] = true;
            // Значения — строки (как в форме подформы), чтобы настройки без потерь открывались для правки.
            $points[] = [
                'key' => $key, 'name' => $name, 'address' => $address, 'phone' => $text('phone', 100), 'email' => $email,
                'hours' => $text('hours', 500), 'info' => $text('info', 1000), 'term' => $text('term', 100),
                'coords' => $coords,
            ];
        }

        return $points;
    }

    /** Адрес в подписи метки: `active` — у выбранной (по умолчанию), `all` — у всех, `none` — не показывать (старые значения 1/0 понимаются). */
    private static function markerAddressMode(mixed $value): string
    {
        $value = (string) $value;

        return match ($value) {
            'all', '1' => 'all',
            'none', '0' => 'none',
            default => 'active',
        };
    }

    /**
     * Координаты в том виде, как их даёт Яндекс Карты: «[55.753994, 37.622093]» (широта, долгота); принимаются также без скобок,
     * через запятую, точку с запятой или пробел и с десятичной запятой. Возвращает [широта, долгота] строками или null.
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

    /** @return array<int, array<string, string>> */
    private function pointsOf(Method $method): array
    {
        return array_values(array_filter((array) ($method->params['points'] ?? []), 'is_array'));
    }

    /** Сведения пункта обычным текстом: адрес, телефон, e-mail, часы работы, срок, дополнительная информация. */
    private function pointRows(array $point): array
    {
        return [
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_ADDRESS'), 'value' => (string) ($point['address'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_PHONE'), 'value' => (string) ($point['phone'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_EMAIL'), 'value' => (string) ($point['email'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_HOURS'), 'value' => (string) ($point['hours'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_TERM'), 'value' => (string) ($point['term'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_INFO'), 'value' => (string) ($point['info'] ?? '')],
        ];
    }

    public function quote(Method $method, Context $context): ?Quote
    {
        $p     = $method->params;
        $min   = (float) ($p['min_total'] ?? 0);
        $max   = (float) ($p['max_total'] ?? 0);
        $price = (float) ($p['price'] ?? 0);
        $from  = (float) ($p['free_from'] ?? 0);

        if (!$this->pointsOf($method) || ($min > 0 && $context->total < $min) || ($max > 0 && $context->total > $max)) {
            return null;
        }

        $free = $price <= 0 || ($from > 0 && $context->total >= $from);

        return new Quote($free ? 0.0 : round($price, 2), (string) ($p['term'] ?? ''), '', $free);
    }

    /** Один пункт — его сведения постоянны для способа; при нескольких пунктах сведения зависят от выбора покупателя. */
    public function describe(Method $method, Quote $quote): array
    {
        $points = $this->pointsOf($method);

        return count($points) === 1 ? $this->pointRows($points[0]) : [];
    }

    public function validateInput(Method $method, Context $context, array $input): array
    {
        $points = $this->pointsOf($method);

        if (count($points) === 1) {
            return ['data' => ['point' => (string) $points[0]['key']], 'rows' => [], 'errors' => []];
        }

        $key = (string) ($input['point'] ?? '');

        foreach ($points as $point) {
            if ((string) $point['key'] === $key) {
                return [
                    'data' => ['point' => $key],
                    'rows' => array_merge([['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT'), 'value' => (string) $point['name']]], $this->pointRows($point)),
                    'errors' => [],
                ];
            }
        }

        return ['data' => [], 'rows' => [], 'errors' => ['point' => 'PLG_DHARMASHOPSHIPPING_PICKUP_POINT_REQUIRED']];
    }

    public function render(array $displayData): string
    {
        $path = PluginHelper::getLayoutPath('dharmashopshipping', 'pickup', 'default');
        $points = [];

        $all = $this->pointsOf($displayData['method']);
        $selectedKey = (string) ($displayData['values']['point'] ?? '');
        $active = in_array($selectedKey, array_column($all, 'key'), true) ? $selectedKey : (string) ($all[0]['key'] ?? '');

        foreach ($all as $point) {
            // Карта одна на способ: у каждого пункта своя ссылка (центр на пункте, остальные пункты — метками).
            $point['map_url'] = $this->mapUrl($displayData['method'], $all, (string) $point['key']);
            $point['rows'] = array_values(array_filter($this->pointRows($point), static fn (array $row): bool => trim($row['value']) !== ''));
            // В карточке — название и адрес; остальное (телефон, часы, срок, как добраться) — блок ниже у выбранного пункта.
            $point['detail_rows'] = array_values(array_filter(\array_slice($this->pointRows($point), 1), static fn (array $row): bool => trim($row['value']) !== ''));
            $points[] = $point;
        }

        $displayData['points'] = $points;
        $displayData['point_selected'] = $active;
        $displayData['map_url'] = (string) (array_column($points, 'map_url', 'key')[$active] ?? '');
        $displayData['map'] = $this->mapDisplay($displayData['method'], $points, $active);

        // Карточки пунктов в слайдере Swiper (его набор регистрирует плагин «Dharma Shop Swiper»), если пунктов три и больше; иначе — сетка.
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/plg_dharmashopshipping_pickup/joomla.asset.json');
        $displayData['slider'] = \count($points) >= 3 && $wa->getRegistry()->exists('preset', 'dharmashop.swiper');

        if ($displayData['slider']) {
            $wa->usePreset('dharmashop.swiper')->useScript('plg_dharmashopshipping_pickup.cards');
        }

        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }

    /**
     * Адрес простого виджета Яндекс Карт (без ключа API): все пункты метками (выбранный — крупной), центр на выбранном
     * либо рамка по всем пунктам при масштабе 0. Пустая строка — карты нет или карту строит скрипт по ключу (mapDisplay).
     */
    private function mapUrl(Method $method, array $points, string $activeKey): string
    {
        $zoom = (int) ($method->params['zoom'] ?? 0);
        $located = [];

        foreach ($points as $point) {
            $parsed = self::parseCoords((string) ($point['coords'] ?? ''));

            if ($parsed !== null) {
                $located[(string) $point['key']] = [(float) $parsed[0], (float) $parsed[1]];
            }
        }

        if ((string) ($method->params['map'] ?? 'none') !== 'yandex' || trim((string) ($method->params['yandex_apikey'] ?? '')) !== '' || !isset($located[$activeKey])) {
            return '';
        }

        [$lat, $lon] = $located[$activeKey];
        $marks = [];

        foreach ($located as $key => [$pLat, $pLon]) {
            $marks[] = $pLon . ',' . $pLat . ',' . ($key === $activeKey ? 'pm2rdl' : 'pm2gnm');
        }

        $url = 'https://yandex.ru/map-widget/v1/?pt=' . rawurlencode(implode('~', $marks));

        // Масштаб 0 — показать все пункты, если их несколько; иначе центр на выбранном.
        if ($zoom === 0 && count($located) > 1) {
            $lats = array_column($located, 0);
            $lons = array_column($located, 1);
            $padLat = max(0.005, (max($lats) - min($lats)) * 0.15);
            $padLon = max(0.005, (max($lons) - min($lons)) * 0.15);

            return $url . '&bbox=' . rawurlencode((min($lons) - $padLon) . ',' . (min($lats) - $padLat) . '~' . (max($lons) + $padLon) . ',' . (max($lats) + $padLat));
        }

        return $url . '&ll=' . rawurlencode($lon . ',' . $lat) . '&z=' . ($zoom ?: 16);
    }

    /**
     * Описание карты для макета: режим (`js` — карта Яндекса JavaScript API 3 по ключу, `iframe` — простой виджет без ключа, `none`), пропорции и конфигурация для скрипта.
     *
     * @return  array{mode: string, ratio: string, config: string}
     */
    private function mapDisplay(Method $method, array $points, string $active): array
    {
        $p = $method->params;
        $kind = (string) ($p['map'] ?? 'none');
        $ratio = in_array((string) ($p['map_ratio'] ?? ''), self::RATIOS, true) ? (string) $p['map_ratio'] : '16x9';
        $key = trim((string) ($p['yandex_apikey'] ?? ''));

        if ($kind === 'none') {
            return ['mode' => 'none', 'ratio' => $ratio, 'config' => ''];
        }

        if ($kind !== 'yandex') {
            return ['mode' => 'none', 'ratio' => $ratio, 'config' => ''];
        }

        if ($key === '') {
            return ['mode' => 'iframe', 'ratio' => $ratio, 'config' => ''];
        }

        $located = [];

        foreach ($points as $point) {
            $parsed = self::parseCoords((string) ($point['coords'] ?? ''));

            if ($parsed !== null) {
                $located[] = ['key' => (string) $point['key'], 'name' => (string) $point['name'], 'address' => (string) $point['address'], 'coords' => [(float) $parsed[0], (float) $parsed[1]]];
            }
        }

        $center = self::parseCoords((string) ($p['center'] ?? ''));
        $config = [
            'apikey' => $key,
            'zoom' => (int) ($p['zoom'] ?? 0),
            'center' => $center !== null ? [(float) $center[0], (float) $center[1]] : null,
            'active' => $active,
            'selectedZoom' => (int) ($p['zoom_selected'] ?? 15),
            'markerKind' => in_array((string) ($p['marker_kind'] ?? ''), self::MARKER_KINDS, true) ? (string) $p['marker_kind'] : 'pin',
            'markerAddress' => self::markerAddressMode($p['marker_address'] ?? null),
            'markerColor' => in_array((string) ($p['marker_color'] ?? ''), self::COLORS, true) ? (string) $p['marker_color'] : 'blue',
            'selectedColor' => in_array((string) ($p['marker_selected_color'] ?? ''), self::COLORS, true) ? (string) $p['marker_selected_color'] : 'red',
            'points' => $located,
        ];

        if (!$located) {
            return ['mode' => 'none', 'ratio' => $ratio, 'config' => ''];
        }

        // Скрипт карты подключается только тогда, когда карта действительно нужна.
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/plg_dharmashopshipping_pickup/joomla.asset.json');
        $wa->useScript('plg_dharmashopshipping_pickup.map');

        return ['mode' => 'js', 'ratio' => $ratio, 'config' => (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)];
    }
}
