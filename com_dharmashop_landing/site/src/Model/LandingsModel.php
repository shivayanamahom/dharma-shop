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
        // Страница, чья категория-источник закрыта, в список не попадает (как на её собственном адресе — 404).
        $where = 'a.state = 1 AND a.broken = 0 AND a.access IN (' . implode(',', $levels) . ') AND (a.catid < 2 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'c')
            . ' WHERE c.id = a.catid AND c.published = 1 AND c.access IN (' . implode(',', $levels) . ')))';
        $order = $ordering === 'title' ? 'a.title ASC' : 'a.ordering ASC, a.id ASC';
        $total = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_landing_pages', 'a') . ' WHERE ' . $where)->loadResult();
        $items = $total ? $db->setQuery(
            'SELECT a.id, a.title, a.alias, a.image, a.image_alt, a.introtext FROM ' . $db->quoteName('#__dharmashop_landing_pages', 'a') . ' WHERE ' . $where . ' ORDER BY ' . $order,
            $start,
            $limit
        )->loadObjectList() : [];

        return [$items, $total];
    }
}
