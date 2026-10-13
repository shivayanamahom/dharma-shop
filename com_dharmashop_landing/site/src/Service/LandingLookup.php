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

use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Поиск опубликованной посадочной страницы по категории и каноническому набору условий (для редиректа со страницы фильтра). */
final class LandingLookup
{
    /**
     * @param   array<string, mixed>  $conditions  канонический массив `filter` (алиасы), как его хранит страница
     * @param   int[]                 $levels      уровни доступа посетителя
     *
     * @return  string|null  адрес страницы или null, если такой страницы нет
     */
    public static function urlFor(DatabaseInterface $db, int $catid, array $conditions, array $levels): ?string
    {
        if (!$conditions || !$levels) {
            return null;
        }

        $scope = $catid > 1 ? $catid : 0;
        $hash = sha1($scope . '|' . json_encode($conditions, JSON_UNESCAPED_UNICODE));
        $query = $db->createQuery()->select(['a.id', 'a.catid'])->from($db->quoteName('#__dharmashop_landing_pages', 'a'))
            ->where('a.conditions_hash = :hash')->where('a.state = 1')->where('a.broken = 0')->where('a.source = ' . $db->quote('filter'))
            ->where('a.access IN (' . implode(',', array_map('intval', $levels)) . ')')->order('a.id ASC')->bind(':hash', $hash);
        $page = $db->setQuery($query, 0, 1)->loadObject();

        if (!$page) {
            return null;
        }

        // Категория-источник должна быть доступна посетителю (иначе у страницы 404).
        if ((int) $page->catid > 1) {
            $category = $db->setQuery(
                $db->createQuery()->select('1')->from($db->quoteName('#__categories'))->where('id = :c')->where('published = 1')
                    ->where('access IN (' . implode(',', array_map('intval', $levels)) . ')')->bind(':c', $page->catid, ParameterType::INTEGER)
            )->loadResult();

            if (!$category) {
                return null;
            }
        }

        return Route::_('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $page->id, false);
    }
}
