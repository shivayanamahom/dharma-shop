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

/** Фильтры значений полей формы (атрибут filter). Данные формы приходят из Registry: ассоциативные массивы там — объекты. */
final class FormFilters
{
    /**
     * Порядок товара в категориях: catid => {action: first|last|manual, sort_order: int}.
     * Номера категорий и значения проверяются здесь; принадлежность товару — в Indexer::setCategoryOrder.
     *
     * @return  array<int, array{action: string, sort_order: int}>
     */
    public static function categoryOrder(mixed $value): array
    {
        $value = is_array($value) || is_object($value) ? (json_decode((string) json_encode($value), true) ?: []) : [];
        $result = [];

        foreach ($value as $catid => $row) {
            $catid = (int) $catid;

            if ($catid <= 0 || !is_array($row)) {
                continue;
            }

            $action = (string) ($row['action'] ?? 'manual');

            if (!in_array($action, ['first', 'last', 'manual'], true)) {
                continue;
            }

            $result[$catid] = [
                'action' => $action,
                'sort_order' => max(-2147483648, min(2147483647, (int) ($row['sort_order'] ?? 0))),
            ];
        }

        return $result;
    }
}
