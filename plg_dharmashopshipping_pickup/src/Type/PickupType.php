<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Pickup\Type;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Dharma\Component\DharmaShop\Administrator\Shipping\Method;
use Dharma\Component\DharmaShop\Administrator\Shipping\Quote;
use Dharma\Component\DharmaShop\Administrator\Shipping\TypeInterface;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Самовывоз: несколько пунктов выдачи (складов) в одном способе — повторяемая форма; у пункта адрес, контакты, часы работы,
 * дополнительные сведения и карта. Покупатель выбирает пункт; цена и ограничения по сумме общие для способа.
 */
final class PickupType implements TypeInterface
{
    private const MAPS = ['none', 'yandex', 'osm'];

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
            'points' => $this->normalizePoints((array) ($settings['points'] ?? [])),
        ];

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_RANGE');
        }

        if (!$normalized['points']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_POINTS');
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

            $map = in_array((string) ($row['map'] ?? ''), self::MAPS, true) ? (string) $row['map'] : 'none';
            $coords = '';

            if ($map !== 'none') {
                $parsed = self::parseCoords((string) ($row['coords'] ?? ''));

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
                'map' => $map, 'coords' => $coords, 'zoom' => (string) min(19, max(1, (int) ($row['zoom'] ?? 16))),
            ];
        }

        return $points;
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

        foreach ($this->pointsOf($displayData['method']) as $point) {
            $point['map_url'] = $this->mapUrl($point);
            $point['rows'] = array_values(array_filter($this->pointRows($point), static fn (array $row): bool => trim($row['value']) !== ''));
            $points[] = $point;
        }

        $displayData['points'] = $points;
        $selectedKey = (string) ($displayData['values']['point'] ?? '');
        $displayData['point_selected'] = in_array($selectedKey, array_column($points, 'key'), true) ? $selectedKey : (string) ($points[0]['key'] ?? '');

        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }

    /** Адрес виджета карты пункта (Яндекс Карты или OpenStreetMap по координатам); пустая строка — карты нет. */
    private function mapUrl(array $point): string
    {
        $map = (string) ($point['map'] ?? 'none');
        $parsed = self::parseCoords((string) ($point['coords'] ?? ''));

        if ($parsed === null) {
            return '';
        }

        [$lat, $lon] = [(float) $parsed[0], (float) $parsed[1]];
        $zoom = (int) ($point['zoom'] ?? 16);

        if ($map === 'yandex') {
            return 'https://yandex.ru/map-widget/v1/?ll=' . rawurlencode($lon . ',' . $lat) . '&z=' . $zoom . '&pt=' . rawurlencode($lon . ',' . $lat . ',pm2rdm');
        }

        if ($map === 'osm') {
            $delta = 0.01 / (2 ** max(0, $zoom - 14));
            $bbox = ($lon - $delta * 2) . ',' . ($lat - $delta) . ',' . ($lon + $delta * 2) . ',' . ($lat + $delta);

            return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
        }

        return '';
    }
}
