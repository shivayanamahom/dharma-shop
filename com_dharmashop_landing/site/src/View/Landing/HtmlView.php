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

use Dharma\Component\DharmaShop\Site\View\Category\HtmlView as CategoryHtmlView;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Страница = категория для вывода списка (вид ядра), адреса форм и ссылок ведут на саму страницу. */
class HtmlView extends CategoryHtmlView
{
    public function categoryRoute(int $id): string
    {
        $page = $this->getModel()->getPage();

        if ($this->menuId > 0) {
            return Route::_('index.php?Itemid=' . $this->menuId, false);
        }

        // Подкатегорий и предков у страницы нет: любая «категория» в ссылках — сама страница или категория-источник товара.
        return $id === (int) $page->catid || $id === 0 || $id === (int) $this->item->id
            ? Route::_('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $page->id, false)
            : Route::_('index.php?option=com_dharmashop&view=category&id=' . $id, false);
    }
}
