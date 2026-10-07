<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Product;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Страница товара: пока выводит оболочку макета и заголовок; состав карточки добавляется отдельными макетами. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public object $item;
    public Registry $params;
    public PageMetadata $seo;
    public bool $showHeading = true;
    public string $price = '';
    public string $oldPrice = '';
    public string $discountLabel = '';
    public bool $showDiscount = false;
    public bool $showSaleCountdown = false;

    public function display($tpl = null): void
    {
        $this->item = $this->getModel()->getItem();
        $this->params = ComponentHelper::getParams('com_dharmashop');
        $money = new MoneyFormatter($this->params);
        $min = $this->item->price_min !== null ? $money->format((string) $this->item->price_min) : '';
        $max = $this->item->price_max !== null ? $money->format((string) $this->item->price_max) : '';
        $this->price = $min . ($max !== '' && $max !== $min ? ' — ' . $max : '');
        $this->showDiscount = (int) $this->params->get('product_page_show_discount', 1) === 1 && $this->item->base_price !== null;
        $this->showSaleCountdown = (int) $this->params->get('product_page_sale_countdown', 1) === 1
            && !empty($this->item->sale_end_timestamp);

        if ($this->item->base_price !== null) {
            $this->oldPrice = $money->format($this->item->base_price);
            $display = $this->params->get('product_page_discount_display', 'percent') === 'amount' ? 'amount' : 'percent';
            $percentValue = (float) $this->item->discount_percent;
            $percent = $percentValue > 0 && round($percentValue, 2) === 0.0
                ? '<0,01'
                : rtrim(rtrim(number_format($percentValue, 2, ',', "\u{00A0}"), '0'), ',');
            $this->discountLabel = $display === 'amount'
                ? '−' . $money->format($this->item->discount_amount)
                : '−' . $percent . '%';
        }

        $route = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $this->item->id, false);
        $url = preg_match('~^https?://~i', $route) ? $route : rtrim(Uri::root(), '/') . '/' . ltrim($route, '/');
        $seo = new PageSeo($this->params);
        $this->seo = $seo->resolve('product', $this->item, ProductValues::build($this->item, new MoneyFormatter($this->params)), new Registry(), $url);
        $seo->apply($this->seo, $this->getDocument());

        $layout = (string) $this->params->get('product_layout', 'default');
        $layout = preg_match('/^[a-z0-9_-]+$/i', $layout) && is_file(JPATH_ROOT . '/components/com_dharmashop/tmpl/product/' . $layout . '.php')
            ? $layout : 'default';
        $this->setLayout($layout);

        if ($this->showSaleCountdown) {
            $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.sale-countdown');
        }

        parent::display($tpl);
    }
}
