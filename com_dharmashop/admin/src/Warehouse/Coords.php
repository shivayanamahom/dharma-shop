<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Warehouse;

\defined('_JEXEC') or die;

/** Координаты в виде Яндекса. */
final class Coords
{
    /**
     * «[55.753994, 37.622093]» (широта, долгота); допустимы запись без скобок, разделители запятая/точка с запятой/пробел
     * и десятичная запятая. Возвращает [широта, долгота] или null.
     *
     * @return  array{0: float, 1: float}|null
     */
    public static function parse(string $value): ?array
    {
        $value = trim(str_replace(['[', ']', '(', ')'], ' ', $value));

        if (!preg_match('/^(-?\d+(?:[.,]\d+)?)\s*[;,\s]\s*(-?\d+(?:[.,]\d+)?)$/D', $value, $m)) {
            return null;
        }

        $lat = round((float) str_replace(',', '.', $m[1]), 6);
        $lon = round((float) str_replace(',', '.', $m[2]), 6);

        return abs($lat) > 90 || abs($lon) > 180 ? null : [$lat, $lon];
    }

    /** Строка для поля формы: «56.838011, 60.597474». */
    public static function format(?float $lat, ?float $lon): string
    {
        if ($lat === null || $lon === null) {
            return '';
        }

        return rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.') . ', ' . rtrim(rtrim(number_format($lon, 6, '.', ''), '0'), '.');
    }
}
