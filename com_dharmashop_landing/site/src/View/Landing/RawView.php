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

use Dharma\Component\DharmaShop\Site\View\Category\RawView as CategoryRawView;

\defined('_JEXEC') or die;

/** AJAX-фрагмент списка товаров страницы (`format=raw`): тот же блок, что у категории. */
class RawView extends CategoryRawView
{
    use LandingRoutes;
}
