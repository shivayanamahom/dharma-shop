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
    public function data(object $item, string $mode = 'grid', string $context = 'product.category', string $instance = '', bool $loadAssets = true, array $extraData = []): array
    {
        $mode = in_array($mode, ['grid', 'list', 'table'], true) ? $mode : 'grid';
        $settings = $this->settings;
        $app = Factory::getApplication();
        if ($loadAssets && $this->favoritesEnabled && $app->getDocument() instanceof HtmlDocument) {
            $wa = $app->getDocument()->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $wa->useScript('com_dharmashop.favorites');
            $app->getDocument()->addScriptOptions('com_dharmashop.favorites', [
                'toggleUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.toggle&format=json', false),
                'tokenName' => Session::getFormToken(),
                'error' => \Joomla\CMS\Language\Text::_('COM_DHARMASHOP_FAVORITES_ERROR_GENERIC'),
            ]);
        }
        $images = [];
        foreach ($item->images ?? [] as $image) {
            if ($url = MediaUrl::absolute($image->path)) {
                $images[] = (object) ['url' => $url, 'alt' => $image->alt !== '' ? $image->alt : $item->title];
            }
        }
        if (!$images && ($url = MediaUrl::absolute((string) ($item->cover ?? '')))) {
            $images[] = (object) ['url' => $url, 'alt' => (string) ($item->cover_alt ?: $item->title)];
        }
        $intro = (new Renderer())->render((string) $item->introtext, ProductValues::build($item, $this->money) + GlobalValues::build($settings));
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $slider = $settings->get('product_gallery_type', 'swiper') !== 'bootstrap'
            && $wa->getRegistry()->exists('preset', 'dharmashop.swiper') ? 'swiper' : 'bootstrap';
        if ($loadAssets && count($images) > 1) {
            if ($slider === 'swiper') { $wa->usePreset('dharmashop.swiper')->useScript('com_dharmashop.card-slider'); }
            else { $wa->useScript('bootstrap.carousel'); }
        }
        $catalog = (int) $this->params->get('catalog_mode', 0) === 1;
        $cartMode = $this->cartMode();
        if ($loadAssets && !$catalog) {
            CartAssets::use(Factory::getApplication()->getDocument());
            if ($cartMode === 'qty') { $wa->useScript('com_dharmashop.qty'); }
        }
        $showCountdown = (int) $settings->get('product_card_sale_countdown', 1) === 1;
        if ($loadAssets && $showCountdown && !empty($item->sale_end_timestamp)) { $wa->useScript('com_dharmashop.sale-countdown'); }
        $data = ['item' => $item, 'params' => clone $this->params, 'mode' => $mode, 'cardLayout' => $mode,
            'cardFolder' => 'product-card', 'cardLayoutSource' => 'override', 'instanceId' => $instance . '-' . (int) $item->id,
            'imageContext' => $context . '.' . $mode, 'fields' => $item->fields ?? [],
            'productUrl' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false),
            'categoryTitle' => (string) ($item->category_title ?? ''),
            'categoryUrl' => Route::_('index.php?option=com_dharmashop&view=category&id=' . (int) $item->catid, false),
            'intro' => $intro, 'showIntro' => (int) $this->params->get('product_card_intro', 1) === 1,
            'priceRange' => $this->money->range($item->price_min, $item->price_max, !empty($item->price_free)),
            'oldPrice' => $item->base_price !== null ? $this->money->format($item->base_price) : '',
            'discountAmount' => $item->discount_amount !== null ? $this->money->format($item->discount_amount) : '',
            'discountPercent' => (float) $item->discount_percent,
            'showDiscount' => (int) $settings->get('product_card_show_discount', 1) === 1,
            'discountDisplay' => $settings->get('product_card_discount_display', 'percent') === 'amount' ? 'amount' : 'percent',
            'saleEndTimestamp' => (int) ($item->sale_end_timestamp ?? 0), 'showSaleCountdown' => $showCountdown,
            'saleBadgeId' => (int) $settings->get('sale_badge_id', 0),
            'badges' => array_map(static fn (object $badge): object => (object) ((array) $badge + ['url' => Route::_('index.php?option=com_dharmashop&view=badge&id=' . (int) $badge->id, false)]), $item->badges ?? []),
            'images' => $images, 'sliderType' => $slider, 'catalogMode' => $catalog,
            'cartMode' => $cartMode, 'cartText' => trim((string) $settings->get('cart_button_text', '')),
            'inCartQty' => (float) ($this->cart[(int) $item->id]['qty'] ?? 0),
            'inCartItem' => (int) ($this->cart[(int) $item->id]['item'] ?? 0),
            'favoritesEnabled' => $this->favoritesEnabled,
            'favoriteIcon' => $this->favoritesEnabled ? FavoritesSettings::renderIcon() : '',
            'favorite' => isset($this->favorites[(int) $item->id]),
            'favoritesTokenName' => Session::getFormToken()];
        $data['favoriteMediaActions'] = (new LayoutHooks('productCard', $context, $item, $this->params))->render('mediaActions', [
            'product' => $item, 'favorite' => $data['favorite'], 'productUrl' => $data['productUrl'],
        ]);
        $data['favoriteActions'] = (new LayoutHooks('productCard', $context, $item, $this->params))->render('actions', [
            'product' => $item, 'favorite' => $data['favorite'], 'productUrl' => $data['productUrl'],
        ]);
        return array_merge($data, $extraData);
    }
    public function render(array $data): string
    {
        $basePath = ($data['cardLayoutSource'] ?? 'override') === 'component' ? JPATH_ROOT . '/layouts' : null;

        return (new FileLayout('components.dharmashop.product-card.default', $basePath, ['component' => 'none','client' => 0]))->render($data);
    }
}
