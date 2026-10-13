<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Controller;

use Dharma\Component\DharmaShop\Site\Controller\FilterController as CoreFilterController;

\defined('_JEXEC') or die;

/** Обновление формы фильтра (каскад в режиме «Применить») на посадочной странице: логика ядра, вид `landing` разрешён. */
class FilterController extends CoreFilterController
{
    protected $option = 'com_dharmashop_landing';

    protected function listingViews(): array
    {
        return array_merge(parent::listingViews(), ['landing']);
    }
}
