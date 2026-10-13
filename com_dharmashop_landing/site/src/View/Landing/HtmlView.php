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

\defined('_JEXEC') or die;

/** Страница = категория для вывода списка (вид ядра), адреса форм и ссылок ведут на саму страницу. */
class HtmlView extends CategoryHtmlView
{
    use LandingRoutes;
}
