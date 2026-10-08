<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Pickup\Type;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Dharma\Component\DharmaShop\Administrator\Shipping\Method;
use Dharma\Component\DharmaShop\Administrator\Shipping\Quote;
use Dharma\Component\DharmaShop\Administrator\Shipping\TypeInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Самовывоз: пункт выдачи (склад) с адресом, контактами, часами работы, дополнительными сведениями и картой.
 * Один способ — один пункт; несколько пунктов — несколько способов этого типа. Данных от покупателя не требуется.
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
        $text = static fn (string $key, int $limit): string => mb_substr(trim(strip_tags((string) ($settings[$key] ?? ''))), 0, $limit);

        $normalized = [
            'address' => $text('address', 500),
            'phone' => $text('phone', 100),
            'email' => $text('email', 190),
            'hours' => $text('hours', 500),
            'info' => $text('info', 1000),
            'term' => $text('term', 100),
            'price' => $money($settings['price'] ?? 0, 'PRICE'),
            'free_from' => $money($settings['free_from'] ?? 0, 'FREE_FROM'),
            'min_total' => $money($settings['min_total'] ?? 0, 'MIN_TOTAL'),
            'max_total' => $money($settings['max_total'] ?? 0, 'MAX_TOTAL'),
            'map' => in_array((string) ($settings['map'] ?? ''), self::MAPS, true) ? (string) $settings['map'] : 'none',
            'lat' => '',
            'lon' => '',
            'zoom' => min(19, max(1, (int) ($settings['zoom'] ?? 16))),
        ];

        if ($normalized['address'] === '') {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_ADDRESS');
        }

        if ($normalized['email'] !== '' && !filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_EMAIL');
        }

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_RANGE');
        }

        if ($normalized['map'] !== 'none') {
            $lat = str_replace(',', '.', trim((string) ($settings['lat'] ?? '')));
            $lon = str_replace(',', '.', trim((string) ($settings['lon'] ?? '')));

            if (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_PICKUP_ERROR_COORDS');
            }

            $normalized['lat'] = rtrim(rtrim(number_format((float) $lat, 6, '.', ''), '0'), '.');
            $normalized['lon'] = rtrim(rtrim(number_format((float) $lon, 6, '.', ''), '0'), '.');
        }

        return $normalized;
    }

    public function quote(Method $method, Context $context): ?Quote
    {
        $p     = $method->params;
        $min   = (float) ($p['min_total'] ?? 0);
        $max   = (float) ($p['max_total'] ?? 0);
        $price = (float) ($p['price'] ?? 0);
        $from  = (float) ($p['free_from'] ?? 0);

        if (($min > 0 && $context->total < $min) || ($max > 0 && $context->total > $max)) {
            return null;
        }

        $free = $price <= 0 || ($from > 0 && $context->total >= $from);

        return new Quote($free ? 0.0 : round($price, 2), (string) ($p['term'] ?? ''), '', $free);
    }

    public function describe(Method $method, Quote $quote): array
    {
        $p = $method->params;

        return [
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_ADDRESS'), 'value' => (string) ($p['address'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_PHONE'), 'value' => (string) ($p['phone'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_EMAIL'), 'value' => (string) ($p['email'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_HOURS'), 'value' => (string) ($p['hours'] ?? '')],
            ['label' => Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_INFO'), 'value' => (string) ($p['info'] ?? '')],
        ];
    }

    public function validateInput(Method $method, Context $context, array $input): array
    {
        return ['data' => [], 'rows' => [], 'errors' => []];
    }

    public function render(array $displayData): string
    {
        $path = PluginHelper::getLayoutPath('dharmashopshipping', 'pickup', 'default');
        $p = $displayData['method']->params;
        $map = (string) ($p['map'] ?? 'none');
        $lat = (float) ($p['lat'] ?? 0);
        $lon = (float) ($p['lon'] ?? 0);
        $zoom = (int) ($p['zoom'] ?? 16);
        $displayData['map_url'] = '';

        if ($map === 'yandex') {
            $displayData['map_url'] = 'https://yandex.ru/map-widget/v1/?ll=' . rawurlencode($lon . ',' . $lat) . '&z=' . $zoom . '&pt=' . rawurlencode($lon . ',' . $lat . ',pm2rdm');
        } elseif ($map === 'osm') {
            $delta = 0.01 / (2 ** max(0, $zoom - 14));
            $bbox = ($lon - $delta * 2) . ',' . ($lat - $delta) . ',' . ($lon + $delta * 2) . ',' . ($lat + $delta);
            $displayData['map_url'] = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
        }

        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }
}
