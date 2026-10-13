<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\View\Landing;

use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Адреса форм и ссылок страницы ведут на саму страницу (у неё нет подкатегорий и предков); общий для вида и AJAX-фрагмента. */
trait LandingRoutes
{
    /** Сама страница — и есть «подборка по условиям»: редиректа на посадочную страницу здесь нет. */
    protected function redirectToMatchingLanding(\Dharma\Component\DharmaShop\Site\Model\CategoryModel $model): void
    {
    }

    public function categoryRoute(int $id): string
    {
        $page = $this->getModel()->getPage();

        if ($this->menuId > 0) {
            return Route::_('index.php?Itemid=' . $this->menuId, false);
        }

        return $id === (int) $page->catid || $id === 0 || $id === (int) $this->item->id
            ? Route::_('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $page->id, false)
            : Route::_('index.php?option=com_dharmashop&view=category&id=' . $id, false);
    }
}
