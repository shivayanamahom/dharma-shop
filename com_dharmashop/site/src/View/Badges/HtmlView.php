<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Badges;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\View\Categories\HtmlView as CategoriesHtmlView;

\defined('_JEXEC') or die;

/** Список бейджей: тот же код, что у списка категорий; свои макет страницы и карточка записи. */
class HtmlView extends CategoriesHtmlView
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::badge();
    }
}
