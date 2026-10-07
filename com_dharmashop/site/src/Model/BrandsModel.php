<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;

\defined('_JEXEC') or die;

/** Список брендов (верхний уровень): карточки ведут на страницы брендов. */
class BrandsModel extends CategoriesModel
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::brand();
    }
}
