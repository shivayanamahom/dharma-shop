<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

\defined('_JEXEC') or die;

/** Значение поля-списка в фильтре. */
final class FilterOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $alias,
        public readonly string $title,
        public readonly ?string $color = null,
        public readonly ?string $image = null,
    ) {
    }
}
