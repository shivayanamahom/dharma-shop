<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

\defined('_JEXEC') or die;

/**
 * Типы «категорий» магазина. Технически это секции штатных категорий Joomla (значение столбца extension):
 * категория (дерево, адрес товара), бренд (один на товар, может быть вложенным), бейдж (плашка на карточке, несколько на товар).
 */
final class CategoryTypes
{
    public const CATEGORY = 'com_dharmashop';

    public const BRAND = 'com_dharmashop.brand';

    public const BADGE = 'com_dharmashop.badge';

    /** @return array<string, string> extension => языковой ключ названия типа */
    public static function all(): array
    {
        return [
            self::CATEGORY => 'COM_DHARMASHOP_TYPE_CATEGORY',
            self::BRAND => 'COM_DHARMASHOP_TYPE_BRAND',
            self::BADGE => 'COM_DHARMASHOP_TYPE_BADGE',
        ];
    }

    /** @return string[] */
    public static function extensions(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(?string $extension): bool
    {
        return $extension !== null && isset(self::all()[$extension]);
    }

    /** Допустимый тип или «категория» по умолчанию. */
    public static function normalize(?string $extension): string
    {
        return self::isValid($extension) ? (string) $extension : self::CATEGORY;
    }

    /** Файл формы с дополнительными полями типа (admin/forms/<имя>.xml) или null. */
    public static function formFile(string $extension): ?string
    {
        return match ($extension) {
            self::BRAND => 'category_brand',
            self::BADGE => 'category_badge',
            default => null,
        };
    }
}
