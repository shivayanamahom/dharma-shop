<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;

\defined('_JEXEC') or die;

/** Список бейджей (плоский): карточки ведут на страницы бейджей. */
class BadgesModel extends CategoriesModel
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::badge();
    }
}
