<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Badge;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\View\Category\RawView as CategoryRawView;

\defined('_JEXEC') or die;

/** AJAX-фрагмент списка товаров бейджа (сортировка, вид, порции без смены адреса). */
class RawView extends CategoryRawView
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::badge();
    }
}
