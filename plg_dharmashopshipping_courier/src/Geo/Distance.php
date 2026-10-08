<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Courier\Geo;

\defined('_JEXEC') or die;

/** Расстояния по координатам [широта, долгота] в градусах. */
final class Distance
{
    private const EARTH_KM = 6371.0088;

    /** Расстояние по прямой (формула гаверсинусов), км. */
    public static function straight(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $dLat = deg2rad($latB - $latA);
        $dLon = deg2rad($lonB - $lonA);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Ближайший склад по прямой.
     *
     * @param   array<int, array{coords: array{0: float, 1: float}}>  $warehouses
     *
     * @return  array{index: int, km: float}|null
     */
    public static function nearest(array $warehouses, float $lat, float $lon): ?array
    {
        $best = null;

        foreach ($warehouses as $index => $warehouse) {
            $km = self::straight($lat, $lon, (float) $warehouse['coords'][0], (float) $warehouse['coords'][1]);

            if ($best === null || $km < $best['km']) {
                $best = ['index' => (int) $index, 'km' => $km];
            }
        }

        return $best;
    }
}
