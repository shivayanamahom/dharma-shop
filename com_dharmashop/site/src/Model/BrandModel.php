<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;

\defined('_JEXEC') or die;

/** Страница бренда: вложенные бренды и товары бренда (по индексу, включая вложенные бренды). */
class BrandModel extends CategoryModel
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::brand();
    }
}
