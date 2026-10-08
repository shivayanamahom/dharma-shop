<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Courier\Geo;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Клиент «Геосаджеста» Яндекса (подсказки при вводе): города, улицы, дома. Нужен ключ с подключённым сервисом «API Геосаджеста»;
 * если сервис для ключа не включён, запрос отклоняется (RuntimeException) и вызывающий код переходит на подсказки по геокодеру.
 * Серверные запросы идут с заголовком Referer адреса сайта (ключ, ограниченный по Referer, их принимает). Ответы кешируются на час.
 */
final class Suggest
{
    private const URL = 'https://suggest-maps.yandex.ru/v1/suggest';

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param   string                          $types  типы объектов Яндекса через запятую: locality, street, house
     * @param   array{0: float, 1: float}|null  $near   [широта, долгота] центра предпочтительной области: влияет на порядок
     *
     * @return  array<int, array{label: string, city: string, street: string, house: string}>
     *
     * @throws  \RuntimeException  сервис недоступен или не разрешён ключу
     */
    public function search(string $text, string $types, int $limit = 7, ?array $near = null): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || $this->apiKey === '') {
            return [];
        }

        $params = ['apikey' => $this->apiKey, 'text' => $text, 'lang' => 'ru_RU', 'results' => max(1, min(10, $limit)), 'types' => $types, 'print_address' => 1];

        if ($near !== null) {
            $params['ll'] = $near[1] . ',' . $near[0];
            $params['spn'] = '3,2';
        }

        $id = md5(json_encode($params, JSON_UNESCAPED_UNICODE) ?: '');
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'plg_dharmashopshipping_courier_suggest', 'caching' => true, 'lifetime' => 60]);
        $cached = $cache->get($id);

        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $response = HttpFactory::getHttp(['timeout' => 5])->get(self::URL . '?' . http_build_query($params), ['Referer' => rtrim(Uri::root(), '/') . '/'], 5);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Yandex suggest HTTP ' . $response->getStatusCode());
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            throw new \RuntimeException('Yandex suggest: unexpected response');
        }

        $items = [];

        foreach ($data['results'] as $row) {
            $city = '';
            $street = '';
            $house = '';

            foreach ((array) ($row['address']['component'] ?? []) as $component) {
                $kinds = array_map('strtoupper', (array) ($component['kind'] ?? []));
                $name = (string) ($component['name'] ?? '');

                if (in_array('LOCALITY', $kinds, true)) {
                    $city = $name;
                } elseif (in_array('STREET', $kinds, true)) {
                    $street = $name;
                } elseif (in_array('HOUSE', $kinds, true)) {
                    $house = $name;
                }
            }

            $title = trim((string) ($row['title']['text'] ?? ''));
            $subtitle = trim((string) ($row['subtitle']['text'] ?? ''));
            // Подпись как в списке подсказок Яндекса: «проспект Ленина, 49, Екатеринбург, Свердловская область».
            $label = trim($title . ($subtitle !== '' ? ', ' . $subtitle : '')) ?: trim((string) ($row['address']['formatted_address'] ?? ''));

            if ($label !== '') {
                $items[] = ['label' => $label, 'city' => $city, 'street' => $street, 'house' => $house, 'title' => $title, 'subtitle' => $subtitle];
            }
        }

        $cache->store(json_encode($items, JSON_UNESCAPED_UNICODE) ?: '[]', $id);

        return $items;
    }
}
