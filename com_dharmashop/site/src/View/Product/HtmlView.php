<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Product;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Seo\ProductMetadata;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Site\Support\CartAssets;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\FavoritesSession;
use Dharma\Component\DharmaShop\Site\Support\FavoritesSettings;
use Dharma\Component\DharmaShop\Site\Support\DisplaySettings;
use Joomla\CMS\Session\Session;
use Dharma\Component\DharmaShop\Site\Support\RecentProducts;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
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
    public string $brandUrl = '';
    public bool $showDiscount = false;
    public bool $showSaleCountdown = false;
    /** @var array<string, mixed> параметры макета галереи: type, thumbs, lightbox, loop, id */
    public array $gallery = [];
    public bool $favorite = false;
    public bool $favoritesEnabled = true;
    public string $favoritesTokenName = '';
    public string $favoritesIcon = '';
    /** @var array<string, mixed> данные блока покупки (макет product-card-parts/actions): cartMode, inCartQty и др. */
    public array $buy = [];
    public string $stepperTemplate = '';
    /** @var array<int, array{title: string, fields: array<string, object>}> Непустые группы видимых характеристик. */
    public array $fieldGroups = [];
    /** Страница открыта через пункт меню этого товара (крошки уже содержат его). */
    private bool $menuOwnsPage = false;

    public function display($tpl = null): void
    {
        $this->item = $this->getModel()->getItem();
        $this->params = ComponentHelper::getParams('com_dharmashop');
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $this->menuOwnsPage = $active !== null && ($active->query['view'] ?? '') === 'product'
            && (int) ($active->query['id'] ?? 0) === (int) $this->item->id;
        $displayKeys = [
            'product_gallery_type', 'product_gallery_thumbs', 'product_gallery_loop', 'product_gallery_lightbox',
            'product_page_show_discount', 'product_page_discount_display', 'product_page_sale_countdown', 'product_page_brand_display',
        ];
        DisplaySettings::merge($this->params, $this->item->params, $displayKeys);
        if ($this->menuOwnsPage) {
            DisplaySettings::merge($this->params, $active->getParams(), $displayKeys);
        }
        if ((int) ($this->item->brand_id ?? 0) > 0 && trim((string) ($this->item->brand_title ?? '')) !== '') {
            $this->brandUrl = Route::_('index.php?option=com_dharmashop&view=brand&id=' . (int) $this->item->brand_id . ':' . (string) $this->item->brand_alias);
        }

        $money = new MoneyFormatter($this->params);
        $this->price = $money->range($this->item->price_min, $this->item->price_max, !empty($this->item->price_free));
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
        // Несемантические адреса (`/component/dharmashop/product/205`, `?option=com_dharmashop&view=product&id=205`) — 301 на ЧПУ.
        // Адрес через другой пункт меню (штатное поведение Joomla: ветка меню сохраняется) остаётся рабочим, его склеивает canonical.
        $this->getDocument()->addScriptOptions('com_dharmashop.recentProducts', [
            'productId' => (int) $this->item->id,
            'cookieName' => RecentProducts::cookieName($app),
            'cookiePath' => RecentProducts::cookiePath(),
        ]);
        $requestUri = Uri::getInstance();

        if ($app->getInput()->getMethod() === 'GET' && (str_contains($requestUri->getPath(), '/component/dharmashop/') || $requestUri->hasVar('option'))) {
            $app->redirect($url, 301);
        }

        \Dharma\Component\DharmaShop\Site\Seo\DuplicateGuard::enforce('product');

        // Варианты группы: при настройке «главный вариант» канонический адрес и og:url — первый вариант группы (по номеру); группа нужна и разметке.
        $variantGroup = \Dharma\Component\DharmaShop\Site\Support\ProductVariants::forItem(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), $this->item);
        $canonicalUrl = $url;
        if ($variantGroup !== null && (string) $this->params->get('seo_variant_canonical', 'self') === 'main' && !empty($variantGroup['variants'][0]['url'])) {
            $canonicalUrl = (string) $variantGroup['variants'][0]['url'];
        }

        // Пункт меню этого товара: его заголовок и метаданные важнее общих правил, а цепочка крошек — сам пункт меню.
        $seo = new PageSeo($this->params);
        $this->seo = $seo->resolve('product', $this->item, ProductValues::build($this->item, new MoneyFormatter($this->params)), $this->menuOwnsPage ? $active->getParams() : new Registry(), $canonicalUrl);
        $content = null;
        if ((int) $this->params->get('seo_structureddata', 1) === 1) {
            $min = $this->item->price_min;
            $max = $this->item->price_max;
            // Одно Offer только для одной реальной цены; диапазон вариантов не выдаём за конкретное предложение.
            $offerPrice = $min !== null && $min !== '' && (float) $min >= 0
                && ($max === null || (float) $min === (float) $max)
                && !($money->replacesZero() && (float) $min === 0.0 && empty($this->item->price_free)) ? (string) $min : null;
            $images = [];
            foreach (array_slice((array) ($this->item->gallery ?? []), 0, 10) as $photo) {
                if (!empty($photo->url)) {
                    $images[] = (string) $photo->url;
                }
            }
            // Характеристики: поля, выводимые на странице товара (значения уже с единицами измерения); файлы, галереи, видео и редактор не входят.
            $properties = [];
            foreach ($this->item->fields ?? [] as $field) {
                if (empty($field->values) || in_array((string) $field->type, ['files', 'gallery', 'video', 'editor'], true) || (int) ($field->in_page ?? 1) !== 1) {
                    continue;
                }
                $parts = [];
                foreach ((array) $field->values as $value) {
                    $text = trim(html_entity_decode(strip_tags((string) ($value->text ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
                if ($parts && trim((string) $field->title) !== '') {
                    $properties[] = [trim((string) $field->title), implode(', ', $parts)];
                }
            }
            $saleEnd = !empty($this->item->sale_end_timestamp) ? gmdate('Y-m-d', (int) $this->item->sale_end_timestamp) : null;
            $content = new ProductMetadata(
                (string) $this->item->title, (string) $this->item->sku, (string) $this->item->brand_title,
                ProductValues::introtext($this->text((string) $this->item->introtext)),
                ImageUrl::resolve((string) $this->item->cover, 'seo.social'), $offerPrice,
                strtoupper(trim((string) $this->params->get('currency_code', 'RUB'))), (bool) $this->item->offer_in_stock,
                $images, $properties,
                $offerPrice !== null && $this->item->base_price !== null ? (string) $this->item->base_price : null, $offerPrice !== null ? $saleEnd : null,
                (string) ($this->item->category_title ?? ''),
                $variantGroup,
            );
        }
        $seo->apply($this->seo, $this->getDocument(), $content);

        $layout = trim((string) $this->item->params->get('product_layout', ''));
        if ($layout === '') {
            $layout = (string) $this->params->get('product_layout', 'default');
        }
        if ($this->menuOwnsPage) {
            $menuLayout = trim((string) $active->getParams()->get('product_layout', ''));
            if ($menuLayout !== '') {
                $layout = $menuLayout;
            }
        }
        $layout = preg_match('/^[a-z0-9_-]+$/i', $layout) && is_file(JPATH_ROOT . '/components/com_dharmashop/tmpl/product/' . $layout . '.php')
            ? $layout : 'default';
        $this->setLayout($layout);

        if (!empty($this->item->choices)) {
            $assets = $this->getDocument()->getWebAssetManager();
            $assets->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $assets->useScript('com_dharmashop.product-choice');
            \Joomla\CMS\Language\Text::script('COM_DHARMASHOP_PRODUCT_CHOICE_ERROR');
            \Joomla\CMS\Language\Text::script('COM_DHARMASHOP_PRODUCT_CHOICE_UNAVAILABLE');
        }

        $this->prepareGallery();
        $this->prepareFavorites();
        $this->prepareFieldGroups();
        $this->prepareBreadcrumbs();

        // Корзина: в режиме каталога на страницу ничего не подключается.
        $this->prepareBuy();

        if ($this->showSaleCountdown) {
            $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.sale-countdown');
        }

        $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.recent-products');
        if ($this->favoritesEnabled) {
            $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.favorites');
        }

        parent::display($tpl);
    }

    private function prepareFavorites(): void
    {
        $this->favoritesEnabled = FavoritesSettings::enabled();

        if (!$this->favoritesEnabled) {
            return;
        }

        $app = Factory::getApplication();
        $app->allowCache(false);
        $session = new FavoritesSession($app->bootComponent('com_dharmashop')->getServices()->favorites(), $app);
        if ($list = $session->current()) {
            $this->favorite = in_array((int) $this->item->id, $session->service()->productIds((int) $list->id), true);
        }
        $this->favoritesTokenName = Session::getFormToken();
        $this->favoritesIcon = FavoritesSettings::renderIcon();
        $this->getDocument()->addScriptOptions('com_dharmashop.favorites', [
            'toggleUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.toggle&format=json', false),
            'tokenName' => $this->favoritesTokenName,
            'error' => \Joomla\CMS\Language\Text::_('COM_DHARMASHOP_FAVORITES_ERROR_GENERIC'),
        ]);
    }

    /** Тексты страницы товара с однократными подстановками; HTML сохраняется, значения экранируются. */
    public function text(string $html): string
    {
        return (new Renderer())->renderHtml($html, ProductValues::build($this->item, new MoneyFormatter($this->params))
            + GlobalValues::build($this->params));
    }

    /** Точки расширения товара, доступные также из отдельных шаблонов и переопределений. */
    public function renderHook(string $position, array $data = []): string
    {
        return (new LayoutHooks('product', 'product', $this->item, $this->params, [], $this->seo))->render($position, $data);
    }

    /** Группировка уже загруженных значений без запросов; порядок задаёт EntityFields. */
    private function prepareFieldGroups(): void
    {
        $this->fieldGroups = [];

        foreach ($this->item->fields ?? [] as $alias => $field) {
            if (empty($field->show) || empty($field->values)) {
                continue;
            }

            $id = (int) ($field->group_id ?? 0);
            $this->fieldGroups[$id] ??= ['title' => (string) ($field->group_title ?? ''), 'fields' => []];
            $this->fieldGroups[$id]['fields'][$alias] = $field;
        }
    }

    /**
     * Блок покупки: режим (настройка «Режим кнопки на странице товара»: кнопка, поле количества или кнопка → виджет), состояние
     * товара в корзине и цена. В режиме каталога ничего не выводится и скрипты корзины не подключаются.
     */
    private function prepareBuy(): void
    {
        $catalog = (int) $this->params->get('catalog_mode', 0) === 1;
        $mode = $catalog ? 'button' : (string) $this->params->get('product_page_cart_mode', '');
        $mode = in_array($mode, ['button', 'qty', 'stepper'], true) ? $mode : ((int) $this->params->get('product_page_qty', 1) === 1 ? 'qty' : 'stepper');
        $inCart = ['item' => 0, 'qty' => 0.0];

        if (!$catalog) {
            CartAssets::use($this->getDocument());

            if ($mode === 'stepper') {
                $app = Factory::getApplication();
                $session = new CartSession($app->bootComponent('com_dharmashop')->getServices()->cart(), $app);
                $cart = $session->current();

                if ($cart !== null) {
                    $inCart = $session->service()->productQuantities($cart)[(int) $this->item->id] ?? $inCart;
                    $app->allowCache(false);
                }

                \Dharma\Component\DharmaShop\Site\Support\ProductCardRenderer::$stepperTemplateEmitted = true;
                $this->stepperTemplate = (new FileLayout('components.dharmashop.product-card-parts.stepper', null, ['component' => 'none', 'client' => 0]))->render(['qty' => 1, 'step' => 1, 'min' => 1]);
            }
        }

        $this->buy = [
            'item' => $this->item,
            'priceRange' => $this->price,
            'catalogMode' => $catalog,
            'cartMode' => $mode,
            'inCartQty' => (float) $inCart['qty'],
            'inCartItem' => (int) $inCart['item'],
            'cartText' => trim((string) $this->params->get('cart_button_text', '')),
            'justify' => 'start',
        ];
    }

    /**
     * Хлебные крошки: цепочка категорий товара (ссылки на их страницы по тому же правилу, что адреса категорий) и название товара.
     * Начало цепочки (главная, пункт меню) добавляет Joomla по активному пункту меню.
     */
    private function prepareBreadcrumbs(): void
    {
        if ($this->menuOwnsPage) {
            return;
        }

        $pathway = Factory::getApplication()->getPathway();

        foreach ($this->item->category_path as $category) {
            $pathway->addItem($category->title, Route::_('index.php?option=com_dharmashop&view=category&id=' . $category->id, false));
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
