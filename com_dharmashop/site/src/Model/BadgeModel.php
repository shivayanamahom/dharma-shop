<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;

\defined('_JEXEC') or die;

/** Страница бейджа: товары с этим бейджем. */
class BadgeModel extends CategoryModel
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::badge();
    }
}
