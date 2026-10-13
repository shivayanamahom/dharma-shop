<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

\defined('_JEXEC') or die;

/** Список опубликованных и доступных посетителю посадочных страниц (для пункта меню «Список посадочных страниц»). */
class LandingsModel extends BaseDatabaseModel
{
    /** @return array{0: object[], 1: int} страницы и общее число */
    public function getPage(int $start, int $limit, string $ordering): array
    {
        $db = $this->getDatabase();
        $levels = array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0];
        // Страница, чья категория-источник (или её предок) закрыта, в список не попадает (на её адресе — 404).
        $where = 'a.state = 1 AND a.broken = 0 AND a.access IN (' . implode(',', $levels) . ') AND ' . \Dharma\Component\DharmaShopLanding\Site\Service\CategoryAccess::sql($db, 'a.catid', $levels);
        $order = $ordering === 'title' ? 'a.title ASC' : 'a.ordering ASC, a.id ASC';
        $total = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_landing_pages', 'a') . ' WHERE ' . $where)->loadResult();
        if ($start > 0 && $start >= $total) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $items = $total ? $db->setQuery(
            'SELECT a.id, a.title, a.alias, a.image, a.image_alt, a.introtext FROM ' . $db->quoteName('#__dharmashop_landing_pages', 'a') . ' WHERE ' . $where . ' ORDER BY ' . $order,
            $start,
            $limit
        )->loadObjectList() : [];

        return [$items, $total];
    }
}
