<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Service;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Категория-источник посадочной страницы доступна посетителю, только если открыта вся ветка до корня (как у обычной категории). */
final class CategoryAccess
{
    /** @param int[] $levels */
    public static function isOpen(DatabaseInterface $db, int $catid, array $levels): bool
    {
        if ($catid < 2) {
            return true;
        }

        $levels = array_map('intval', $levels) ?: [0];
        $list = implode(',', $levels);

        return (int) $db->setQuery(
            'SELECT COUNT(*) FROM ' . $db->quoteName('#__categories', 'x') . ' WHERE x.id = ' . $catid . ' AND x.extension = ' . $db->quote('com_dharmashop')
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'c') . ' WHERE c.extension = x.extension AND c.lft <= x.lft AND c.rgt >= x.rgt AND (c.published <> 1 OR c.access NOT IN (' . $list . ')))'
        )->loadResult() === 1;
    }

    /**
     * Условие для списка страниц: `<колонка>` — `catid` страницы. @param int[] $levels
     */
    public static function sql(DatabaseInterface $db, string $column, array $levels): string
    {
        $list = implode(',', array_map('intval', $levels) ?: [0]);

        return '(' . $column . ' < 2 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'x') . ' WHERE x.id = ' . $column . ' AND x.extension = ' . $db->quote('com_dharmashop')
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'c') . ' WHERE c.extension = x.extension AND c.lft <= x.lft AND c.rgt >= x.rgt AND (c.published <> 1 OR c.access NOT IN (' . $list . ')))))';
    }
}
