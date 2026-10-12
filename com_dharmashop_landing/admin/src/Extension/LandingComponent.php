<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Extension;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

\defined('_JEXEC') or die;

/** Точка входа компонента посадочных страниц. Без ядра (`com_dharmashop`) страницы не выводятся. */
final class LandingComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;
}
