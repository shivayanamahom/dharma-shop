<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Extension;

use Joomla\CMS\Extension\MVCComponent;

\defined('_JEXEC') or die;

/**
 * Точка входа компонента. Службы ядра (индексатор, цены, остатки) будут добавляться сюда по этапам.
 */
final class DharmaShopComponent extends MVCComponent
{
}
