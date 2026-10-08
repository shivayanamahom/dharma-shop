<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Courier\Geo;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Расстояния по дорогам: «Матрица расстояний» Яндекса (api.routing.yandex.net/v2/distancematrix, режим driving). Нужен ключ с подключённым
 * сервисом «API Матрицы расстояний/Маршрутизатора»; без него запрос отклоняется (RuntimeException) и вызывающий код считает по прямой.
 * Запросы идут с заголовком Referer адреса сайта; результаты кешируются на сутки.
 */
final class Router
{
    private const URL = 'https://api.routing.yandex.net/v2/distancematrix';

    /** Пределы одного запроса: число складов (origins). */
    private const MAX_ORIGINS = 25;

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * Длина маршрута на автомобиле от каждого склада до точки, км (null — маршрута нет).
     *
     * @param   array<int, array{0: float, 1: float}>  $origins      [широта, долгота] складов
     * @param   array{0: float, 1: float}              $destination  [широта, долгота]
     *
     * @return  array<int, float|null>  в порядке $origins
     *
     * @throws  \RuntimeException  сервис недоступен или не разрешён ключу
     */
    public function kilometers(array $origins, array $destination): array
    {
        $origins = array_slice(array_values($origins), 0, self::MAX_ORIGINS);

        if (!$origins || $this->apiKey === '') {
            return [];
        }

        $point = static fn (array $p): string => round($p[0], 6) . ',' . round($p[1], 6);
        $params = [
            'apikey' => $this->apiKey,
            'origins' => implode('|', array_map($point, $origins)),
            'destinations' => $point($destination),
            'mode' => 'driving',
        ];
        $id = md5(json_encode($params) ?: '');
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'plg_dharmashopshipping_courier_route', 'caching' => true, 'lifetime' => 1440]);
        $cached = $cache->get($id);

        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $response = HttpFactory::getHttp(['timeout' => 8])->get(self::URL . '?' . http_build_query($params), ['Referer' => rtrim(Uri::root(), '/') . '/'], 8);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Yandex distance matrix HTTP ' . $response->getStatusCode());
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) {
            throw new \RuntimeException('Yandex distance matrix: unexpected response');
        }

        $result = [];

        foreach (array_values($data['rows']) as $i => $row) {
            $element = (array) (($row['elements'] ?? [])[0] ?? []);
            $meters = ($element['status'] ?? '') === 'OK' ? ($element['distance']['value'] ?? null) : null;
            $result[$i] = is_numeric($meters) ? round((float) $meters / 1000, 1) : null;
        }

        $cache->store(json_encode($result) ?: '[]', $id);

        return $result;
    }
}
