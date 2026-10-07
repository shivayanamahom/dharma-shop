<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Badge;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\View\Category\HtmlView as CategoryHtmlView;

\defined('_JEXEC') or die;

/** Страница бейджа: тот же код, что у страницы товаров категории; свои макет страницы и карточки (папка шаблонов `badge`). */
class HtmlView extends CategoryHtmlView
{
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::badge();
    }
}
