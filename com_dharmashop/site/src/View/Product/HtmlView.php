<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Product;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Support\CartAssets;
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
    /** @var array<string, mixed> параметры макета галереи: type, thumbs, lightbox, loop, id */
    public array $gallery = [];

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
        // Один адрес на товар: любой другой адрес (старый `/component/dharmashop/product/205`, `?view=product&id=205`) — 301 на основной.
        $app = Factory::getApplication();

        if ($app->getInput()->getMethod() === 'GET' && rtrim(Uri::getInstance()->getPath(), '/') !== rtrim((string) parse_url($url, PHP_URL_PATH), '/')) {
            $app->redirect($url, 301);
        }

        $seo = new PageSeo($this->params);
        $this->seo = $seo->resolve('product', $this->item, ProductValues::build($this->item, new MoneyFormatter($this->params)), new Registry(), $url);
        $seo->apply($this->seo, $this->getDocument());

        $layout = (string) $this->params->get('product_layout', 'default');
        $layout = preg_match('/^[a-z0-9_-]+$/i', $layout) && is_file(JPATH_ROOT . '/components/com_dharmashop/tmpl/product/' . $layout . '.php')
            ? $layout : 'default';
        $this->setLayout($layout);

        $this->prepareGallery();
        $this->prepareBreadcrumbs();

        // Корзина: в режиме каталога на страницу ничего не подключается.
        if ((int) $this->params->get('catalog_mode', 0) !== 1) {
            CartAssets::use($this->getDocument());
        }

        if ($this->showSaleCountdown) {
            $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.sale-countdown');
        }

        parent::display($tpl);
    }

    /**
     * Хлебные крошки: цепочка категорий товара (ссылки на их страницы по тому же правилу, что адреса категорий) и название товара.
     * Начало цепочки (главная, пункт меню) добавляет Joomla по активному пункту меню.
     */
    private function prepareBreadcrumbs(): void
    {
        $menu = Factory::getApplication()->getMenu();
        $catalog = $menu->getItems('link', 'index.php?option=com_dharmashop&view=categories&id=0')
            ?: $menu->getItems('link', 'index.php?option=com_dharmashop&view=categories&id=1');
        $view = $catalog ? 'categories' : 'category';
        $pathway = Factory::getApplication()->getPathway();

        foreach ($this->item->category_path as $category) {
            $pathway->addItem($category->title, Route::_('index.php?option=com_dharmashop&view=' . $view . '&id=' . $category->id, false));
        }

        $pathway->addItem($this->seo->breadcrumb !== '' ? $this->seo->breadcrumb : (string) $this->item->title, '');
    }

    /**
     * Галерея: Swiper (плагин «Dharma Shop Swiper» включён и выбран в настройках) или карусель Bootstrap; лайтбокс — Fancybox
     * (плагин «Dharma Shop Fancybox» или, запасным вариантом, WT Fancybox). Скрипты подключаются только при необходимости.
     */
    private function prepareGallery(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $registry = $wa->getRegistry();
        $registry->addExtensionRegistryFile('com_dharmashop');
        $type = $this->params->get('product_gallery_type', 'swiper') === 'bootstrap' ? 'bootstrap' : 'swiper';

        if ($type === 'swiper' && !$registry->exists('preset', 'dharmashop.swiper')) {
            $type = 'bootstrap';
        }

        // Fancybox: набор нашего плагина «Dharma Shop Fancybox»; если его нет — набор стороннего плагина WT Fancybox (wtfancybox).
        $fancybox = $registry->exists('preset', 'dharmashop.fancybox') ? 'dharmashop.fancybox' : ($registry->exists('preset', 'wtfancybox') ? 'wtfancybox' : '');
        $lightbox = $this->params->get('product_gallery_lightbox', 'none') === 'fancybox' && $fancybox !== '';

        if (count($this->item->gallery) > 0) {
            if ($type === 'swiper') {
                $wa->usePreset('dharmashop.swiper');
            } else {
                $wa->useScript('bootstrap.carousel');
            }

            if ($lightbox) {
                $wa->usePreset($fancybox);
            }

            $wa->useScript('com_dharmashop.product-gallery');
        }

        $this->gallery = [
            'id' => (int) $this->item->id,
            'type' => $type,
            'thumbs' => $this->params->get('product_gallery_thumbs', 'bottom') === 'none' ? 'none' : 'bottom',
            'lightbox' => $lightbox,
            'loop' => (int) $this->params->get('product_gallery_loop', 0) === 1,
            'images' => $this->item->gallery,
        ];
    }
}
