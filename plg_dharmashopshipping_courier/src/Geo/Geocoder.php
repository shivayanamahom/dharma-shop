<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Courier\Geo;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * Серверный клиент HTTP Геокодера Яндекса: адрес → координаты и подсказки. Результаты кешируются (сутки), ошибки не кешируются.
 * Ключ тот же, что у карты (JavaScript API и HTTP Геокодер); если он ограничен по адресу сайта (Referer), серверные запросы отклоняются.
 */
final class Geocoder
{
    private const URL = 'https://geocode-maps.yandex.ru/1.x/';

    /** Точности, при которых адрес пригоден для расчёта по расстоянию. */
    public const GOOD_PRECISION = ['exact', 'number', 'near', 'street'];

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param   array{0: float, 1: float}|null  $near  [широта, долгота] центра предпочтительной области (склады): влияет на порядок, не ограничивает
     *
     * @return  array<int, array{text: string, address: string, city: string, street: string, house: string, coords: array{0: float, 1: float}, precision: string}>
     *
     * @throws  \RuntimeException  ошибка сервиса (ключ, лимит, сеть)
     */
    public function search(string $text, int $limit = 5, ?array $near = null): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || $this->apiKey === '') {
            return [];
        }

        $params = ['apikey' => $this->apiKey, 'geocode' => $text, 'format' => 'json', 'results' => max(1, min(10, $limit)), 'lang' => 'ru_RU'];

        if ($near !== null) {
            $lat = $near[0];
            $lon = $near[1];
            $params['bbox'] = round($lon - 1.5, 4) . ',' . round($lat - 1.0, 4) . '~' . round($lon + 1.5, 4) . ',' . round($lat + 1.0, 4);
            $params['rspn'] = 0;
        }

        $id = md5(json_encode($params, JSON_UNESCAPED_UNICODE) ?: '');
        $cache = $this->cache();
        $cached = $cache->get($id);

        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $response = HttpFactory::getHttp(['timeout' => 6])->get(self::URL . '?' . http_build_query($params), [], 6);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Yandex geocoder HTTP ' . $response->getStatusCode());
        }

        $result = $this->parse((string) $response->getBody());
        $cache->store(json_encode($result, JSON_UNESCAPED_UNICODE) ?: '[]', $id);

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function parse(string $body): array
    {
        $data = json_decode($body, true);
        $members = $data['response']['GeoObjectCollection']['featureMember'] ?? null;

        if (!is_array($members)) {
            throw new \RuntimeException('Yandex geocoder: unexpected response');
        }

        $items = [];

        foreach ($members as $member) {
            $object = (array) ($member['GeoObject'] ?? []);
            $meta = (array) ($object['metaDataProperty']['GeocoderMetaData'] ?? []);
            $pos = explode(' ', (string) ($object['Point']['pos'] ?? ''));

            if (count($pos) !== 2 || !is_numeric($pos[0]) || !is_numeric($pos[1])) {
                continue;
            }

            $components = (array) ($meta['Address']['Components'] ?? []);
            $city = '';
            $street = '';
            $house = '';
            $parts = [];

            foreach ($components as $component) {
                $kind = (string) ($component['kind'] ?? '');
                $name = (string) ($component['name'] ?? '');

                if ($kind === 'locality') {
                    $city = $name;
                } elseif (in_array($kind, ['street', 'house'], true)) {
                    $parts[] = $name;
                    ${$kind} = $name;
                }
            }

            $formatted = (string) ($meta['Address']['formatted'] ?? $meta['text'] ?? '');
            $items[] = [
                'text' => $formatted,
                // Адрес без страны/региона/города для подстановки в поле «Адрес», если город вводится отдельно.
                'address' => $parts ? implode(', ', $parts) : $formatted,
                'city' => $city,
                'street' => $street,
                'house' => $house,
                'coords' => [(float) $pos[1], (float) $pos[0]],
                'precision' => (string) ($meta['precision'] ?? 'other'),
            ];
        }

        return $items;
    }

    private function cache(): object
    {
        return Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'plg_dharmashopshipping_courier', 'caching' => true, 'lifetime' => 1440]);
    }
}
