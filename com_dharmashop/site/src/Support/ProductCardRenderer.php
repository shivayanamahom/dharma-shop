<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
/** Общие данные карточек для расширений: цены, изображения, поля и состояние корзины. SQL не выполняется на каждый товар. */
final class ProductCardRenderer
{
    private MoneyFormatter $money;
    private array $cart = [];
    private array $favorites = [];
    private Registry $settings;
    private bool $favoritesEnabled;
    private string $favoritesTokenName = '';
    private bool $favoritesAssetsRegistered = false;
    private ?string $favoriteIcon = null;
    private ?array $globalValues = null;
    private ?Renderer $placeholderRenderer = null;
    private ?string $sliderType = null;
    private bool $registryLoaded = false;
    private bool $cartAssetsUsed = false;
    private bool $qtyScriptUsed = false;
    private bool $countdownScriptUsed = false;
    /** @var array<int, string> */
    private array $categoryRoutes = [];
    /** @var array<int, string> */
    private array $badgeRoutes = [];
    /** @var array<string, FileLayout> */
    private array $cardLayouts = [];
    public function __construct(private readonly Registry $params, ?array $cartQuantities = null)
    {
        $this->settings = ComponentHelper::getParams('com_dharmashop');
        $this->favoritesEnabled = FavoritesSettings::enabled();
        $this->money = new MoneyFormatter($this->settings);
        if ($cartQuantities !== null) { $this->cart = $cartQuantities; }
        if ($cartQuantities === null && (int) $params->get('catalog_mode', 0) !== 1 && $this->cartMode() === 'stepper') {
            $app = Factory::getApplication();
            $session = new CartSession($app->bootComponent('com_dharmashop')->getServices()->cart(), $app);
            if ($cart = $session->current()) {
                $this->cart = $session->service()->productQuantities($cart);
                $app->allowCache(false);
            }
        }
        $app = Factory::getApplication();
        if ($app->isClient('site') && $this->favoritesEnabled) {
            // Кнопка избранного встраивает в HTML/scriptOptions сессионный CSRF-токен, поэтому общий Page Cache для таких страниц небезопасен.
            $app->allowCache(false);
            $favorites = new FavoritesSession($app->bootComponent('com_dharmashop')->getServices()->favorites(), $app);
            if ($list = $favorites->current()) {
                $this->favorites = array_fill_keys($favorites->service()->productIds((int) $list->id), true);
            }
        }
    }
    private function cartMode(): string
    {
        $mode = (string) $this->params->get('product_card_cart_mode', '');

        return in_array($mode, ['button','qty','stepper'], true) ? $mode
            : ((int) $this->params->get('product_card_qty', 0) === 1 ? 'qty' : 'stepper');
    }

    /** Данные избранного и плагиновых слотов карточки: переиспользуется списком категории и общим рендерером без дублей. */
    public function favoriteData(object $item, string $context, string $productUrl, bool $loadAssets = true): array
    {
        $app = Factory::getApplication();
        $doc = $app->getDocument();

        if ($loadAssets && $this->favoritesEnabled && !$this->favoritesAssetsRegistered && $doc instanceof HtmlDocument) {
            $this->favoritesAssetsRegistered = true;
            $this->favoritesTokenName = Session::getFormToken();
            $wa = $doc->getWebAssetManager();
            $this->ensureRegistry($wa);
            $wa->useScript('com_dharmashop.favorites');
            $doc->addScriptOptions('com_dharmashop.favorites', [
                'toggleUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.toggle&format=json', false),
                'tokenName' => $this->favoritesTokenName,
                'error' => \Joomla\CMS\Language\Text::_('COM_DHARMASHOP_FAVORITES_ERROR_GENERIC'),
            ]);
        }

        $favorite = isset($this->favorites[(int) $item->id]);
        $hooks = new LayoutHooks('productCard', $context, $item, $this->params);

        return [
            'favoritesEnabled' => $this->favoritesEnabled,
            'favoriteIcon' => $this->favoritesEnabled ? ($this->favoriteIcon ??= FavoritesSettings::renderIcon()) : '',
            'favorite' => $favorite,
            'favoritesTokenName' => $this->favoritesEnabled ? ($this->favoritesTokenName ?: ($this->favoritesTokenName = Session::getFormToken())) : '',
            'favoriteMediaActions' => $hooks->render('mediaActions', [
                'product' => $item, 'favorite' => $favorite, 'productUrl' => $productUrl,
            ]),
            'favoriteActions' => $hooks->render('actions', [
                'product' => $item, 'favorite' => $favorite, 'productUrl' => $productUrl,
            ]),
        ];
    }

    public function data(object $item, string $mode = 'grid', string $context = 'product.category', string $instance = '', bool $loadAssets = true, array $extraData = []): array
    {
        $mode = in_array($mode, ['grid', 'list', 'table'], true) ? $mode : 'grid';
        $settings = $this->settings;
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();
        $this->ensureRegistry($wa);

        $images = [];
        foreach ($item->images ?? [] as $image) {
            if ($url = MediaUrl::absolute($image->path)) {
                $images[] = (object) ['url' => $url, 'alt' => $image->alt !== '' ? $image->alt : $item->title];
            }
        }
        if (!$images && ($url = MediaUrl::absolute((string) ($item->cover ?? '')))) {
            $images[] = (object) ['url' => $url, 'alt' => (string) ($item->cover_alt ?: $item->title)];
        }
        $intro = '';
        $showIntro = (int) $this->params->get('product_card_intro', 1) === 1;
        if ($showIntro) {
            $this->globalValues ??= GlobalValues::build($settings);
            $this->placeholderRenderer ??= new Renderer();
            $intro = $this->placeholderRenderer->render((string) $item->introtext, ProductValues::build($item, $this->money) + $this->globalValues);
        }
        $slider = $this->sliderType ??= ($settings->get('product_gallery_type', 'swiper') !== 'bootstrap'
            && $wa->getRegistry()->exists('preset', 'dharmashop.swiper') ? 'swiper' : 'bootstrap');
        if ($loadAssets && count($images) > 1) {
            if ($slider === 'swiper') { $wa->usePreset('dharmashop.swiper')->useScript('com_dharmashop.card-slider'); }
            else { $wa->useScript('bootstrap.carousel'); }
        }
        $catalog = (int) $this->params->get('catalog_mode', 0) === 1;
        $cartMode = $this->cartMode();
        // AJAX-фрагмент списка (`format=raw`) отдаёт только разметку: скрипты и опции страницы в нём не нужны (страница их уже подключила).
        if ($loadAssets && !$catalog && $doc instanceof HtmlDocument) {
            if (!$this->cartAssetsUsed) {
                CartAssets::use($doc);
                $this->cartAssetsUsed = true;
            }
            if ($cartMode === 'qty' && !$this->qtyScriptUsed) {
                $wa->useScript('com_dharmashop.qty');
                $this->qtyScriptUsed = true;
            }
        }
        $showCountdown = (int) $this->params->get('product_card_sale_countdown', $settings->get('product_card_sale_countdown', 1)) === 1;
        if ($loadAssets && $showCountdown && !empty($item->sale_end_timestamp) && !$this->countdownScriptUsed) {
            $wa->useScript('com_dharmashop.sale-countdown');
            $this->countdownScriptUsed = true;
        }
        $productUrl = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false);
        $catId = (int) $item->catid;
        $data = ['item' => $item, 'params' => clone $this->params, 'mode' => $mode, 'cardLayout' => $mode,
            'cardFolder' => 'product-card', 'cardLayoutSource' => 'override', 'instanceId' => $instance . '-' . (int) $item->id,
            'imageContext' => $context . '.' . $mode, 'fields' => $item->fields ?? [],
            'productUrl' => $productUrl,
            'categoryTitle' => (string) ($item->category_title ?? ''),
            'categoryUrl' => $this->categoryRoutes[$catId] ??= Route::_('index.php?option=com_dharmashop&view=category&id=' . $catId, false),
            'intro' => $intro, 'showIntro' => $showIntro,
            'priceRange' => $this->money->range($item->price_min, $item->price_max, !empty($item->price_free)),
            'oldPrice' => $item->base_price !== null ? $this->money->format($item->base_price) : '',
            'discountAmount' => $item->discount_amount !== null ? $this->money->format($item->discount_amount) : '',
            'discountPercent' => (float) $item->discount_percent,
            'showDiscount' => (int) $this->params->get('product_card_show_discount', $settings->get('product_card_show_discount', 1)) === 1,
            'discountDisplay' => $this->params->get('product_card_discount_display', $settings->get('product_card_discount_display', 'percent')) === 'amount' ? 'amount' : 'percent',
            'saleEndTimestamp' => (int) ($item->sale_end_timestamp ?? 0), 'showSaleCountdown' => $showCountdown,
            'saleBadgeId' => (int) $settings->get('sale_badge_id', 0),
            'badges' => array_map(fn (object $badge): object => (object) ((array) $badge + ['url' => $this->badgeRoutes[(int) $badge->id] ??= Route::_('index.php?option=com_dharmashop&view=badge&id=' . (int) $badge->id, false)]), $item->badges ?? []),
            'images' => $images, 'sliderType' => $slider, 'catalogMode' => $catalog,
            'cartMode' => $cartMode, 'cartText' => trim((string) $settings->get('cart_button_text', '')),
            'quickOrder' => \Dharma\Component\DharmaShop\Administrator\Support\QuickOrder::forPlace('card'),
            'inCartQty' => (float) ($this->cart[(int) $item->id]['qty'] ?? 0),
            'inCartItem' => (int) ($this->cart[(int) $item->id]['item'] ?? 0)];
        $data += $this->favoriteData($item, $context, $productUrl, $loadAssets);
        return array_merge($data, $extraData);
    }

    private function ensureRegistry(object $wa): void
    {
        if (!$this->registryLoaded) {
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $this->registryLoaded = true;
        }
    }

    /**
     * Шаблон виджета «кнопка → виджет» уже выведен на странице (представлением списка или товара, либо первой карточкой расширения).
     * cart.js клонирует его из `template[data-dharmashop-stepper-template]`; без него в модуле товаров кнопка не превращается в виджет.
     */
    public static bool $stepperTemplateEmitted = false;
    public function render(array $data): string
    {
        $source = (string) ($data['cardLayoutSource'] ?? 'override');
        $basePath = $source === 'component' ? JPATH_ROOT . '/layouts' : null;
        $layout = $this->cardLayouts[$source] ??= new FileLayout('components.dharmashop.product-card.default', $basePath, ['component' => 'none', 'client' => 0]);
        $html = $layout->render($data);

        if (($data['cartMode'] ?? '') === 'stepper' && empty($data['catalogMode']) && !self::$stepperTemplateEmitted
            && Factory::getApplication()->getDocument() instanceof HtmlDocument && Factory::getApplication()->getInput()->getCmd('format', 'html') === 'html') {
            self::$stepperTemplateEmitted = true;
            $html .= '<template data-dharmashop-stepper-template>'
                . (new FileLayout('components.dharmashop.product-card-parts.stepper', null, ['component' => 'none', 'client' => 0]))->render(['qty' => 1, 'step' => 1, 'min' => 1])
                . '</template>';
        }

        return $html;
    }
}
