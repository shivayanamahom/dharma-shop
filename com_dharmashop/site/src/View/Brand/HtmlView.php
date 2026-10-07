<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Brand;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\View\Category\HtmlView as CategoryHtmlView;

\defined('_JEXEC') or die;

/** Страница бренда: тот же код, что у страницы товаров категории; свои макет страницы и карточки (папка шаблонов `brand`). */
class HtmlView extends CategoryHtmlView
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::brand();
    }
}
