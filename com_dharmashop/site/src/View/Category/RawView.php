<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Category;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

/**
 * AJAX-фрагмент списка товаров категории (`format=raw`): только блок товаров и ссылки порций, без страницы.
 * Сортировка и вид приходят параметрами `sort` и `product_view`; адрес страницы в браузере при этом не меняется.
 */
class RawView extends BaseHtmlView
{
    use ListingTrait;

    protected $option = 'com_dharmashop';

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->prepareListing($model, $model->getAncestors());
        $this->loadProducts($model);

        // Фрагмент не страница: поисковикам он не нужен.
        Factory::getApplication()->setHeader('X-Robots-Tag', 'noindex, follow');

        parent::display('items');
    }
}
