<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Checkout;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Administrator\Order\CheckoutFields;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Payment\Context as PaymentContext;
use Dharma\Component\DharmaShop\Administrator\Shipping\Context as ShippingContext;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\CheckoutPrefill;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Контакты/согласия из настроек, гостевой доступ и состав текущей корзины. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    public array $contacts = [];
    public array $agreements = [];
    public array $lines = [];
    public array $totals = ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0, 'adjustments' => [], 'total' => 0.0];
    public MoneyFormatter $money;
    public string $cartUrl = '';
    public bool $loginRequired = false;
    public string $loginUrl = '';
    public string $submitKey = '';
    public array $values = [];
    public array $errors = [];
    public ?array $receipt = null;
    /** @var array<int, array{id: int, selected: bool, free: bool, image_url: string, total_text: string, title: string, term: string, price_text: string, info: array, html: string}> варианты доставки: id способа => данные плитки-вкладки, сведения для боковой панели и готовый HTML панели варианта (макет плагина типа) */
    public array $shippingOptions = [];
    public int $shippingSelected = 0;
    public float $shippingPrice = 0.0;
    /** Цена выбранного способа ещё не рассчитана (зависит от адреса). */
    public bool $shippingPending = false;
    /** Способы доставки включены, но для этой корзины ни один не подходит: заказ оформить нельзя. */
    public bool $shippingBlocked = false;
    /** @var array<string, mixed> данные плитки способа для default_shipping_tile.php */
    public array $shippingTile = [];
    /** @var array<int, array{id: int, selected: bool, visible: bool, image_url: string, title: string, info: array, html: string}> способы оплаты: id => данные плитки и готовый HTML панели (макет плагина типа) */
    public array $paymentOptions = [];
    public int $paymentSelected = 0;
    /** Способы оплаты есть, но для этой суммы заказа ни один не подходит: заказ оформить нельзя. */
    public bool $paymentBlocked = false;
    /** JSON «id доставки → id разрешённых способов оплаты» (пусто — любые): скрипт оформления скрывает недоступную оплату при смене доставки. */
    public string $paymentMap = '{}';
    /** @var array<string, mixed> данные плитки способа оплаты для default_payment_tile.php */
    public array $paymentTile = [];

    /** Оформление «в один шаг» (C100): общая настройка `checkout_mode` или макет `onepage` пункта меню. */
    public bool $onepage = false;
    public string $catalogUrl = '';

    public function totalText(float $amount): string
    {
        $zero = $this->money->replacesZero()
            && array_filter($this->lines, static fn (object $line): bool => $line->error === '' && $line->unit_price === 0.0 && empty($line->zero_free));

        return $zero
            ? ($amount > 0 ? $this->money->format((string) $amount) . ' + ' : '') . $this->money->zeroText()
            : $this->money->price((string) $amount, true);
    }

    public function discountTotals(): array
    {
        $before = 0.0;
        $actual = 0.0;

        foreach ($this->lines as $line) {
            if ($line->error !== '' || $line->unit_price === null) {
                continue;
            }

            $regular = $line->base_price !== null && $line->base_price > $line->unit_price ? $line->base_price : $line->unit_price;
            $before += round($regular * $line->qty, 2);
            $actual += $line->total;
        }

        return ['before' => round($before, 2), 'saved' => round($before - $actual, 2)];
    }

    /** Итог к оплате с учётом доставки выбранного варианта. */
    public function totalWithShipping(): float
    {
        return round((float) $this->totals['total'] + $this->shippingPrice, 2);
    }

    /** Способы доставки с расчётом сервера: цена и доступность определяются плагином типа; HTML варианта — макет плагина. */
    private function prepareShipping($app, $settings): void
    {
        $valid = array_values(array_filter($this->lines, static fn (object $line): bool => $line->error === ''));

        if (!$valid) {
            return;
        }

        $service = $app->bootComponent('com_dharmashop')->getServices()->shipping();
        $context = new ShippingContext((float) $this->totals['total'], (float) $this->totals['subtotal'], (float) $this->totals['qty'], (int) $this->totals['count'], (string) $settings->get('currency_code', 'RUB'), $valid, $this->values);
        $options = $service->options($context);

        if (!$options) {
            $this->shippingBlocked = $service->hasMethods();

            return;
        }

        $state = (array) $app->getUserState('com_dharmashop.checkout.shipping', []);
        $chosen = (int) ($state['method'] ?? 0);
        $this->shippingSelected = isset($options[$chosen]) ? $chosen : $service->defaultId($options);
        $free = Text::_('COM_DHARMASHOP_SHIPPING_FREE');

        foreach ($options as $id => $option) {
            $quote = $option['quote'];

            if ($id === $this->shippingSelected) {
                $this->shippingPrice = $quote->pending ? 0.0 : $quote->price;
                $this->shippingPending = $quote->pending;
            }

            $this->shippingOptions[$id] = [
                'id' => $id,
                'selected' => $id === $this->shippingSelected,
                'free' => $quote->free && !$quote->pending,
                'image_url' => $option['method']->image !== '' ? ImageUrl::resolve($option['method']->image, 'shipping.option') : '',
                'total_text' => $this->totalText(round((float) $this->totals['total'] + ($quote->pending ? 0.0 : $quote->price), 2)),
                'title' => $option['method']->title,
                'term' => $quote->term,
                'price_text' => $quote->pending ? Text::_('COM_DHARMASHOP_SHIPPING_PENDING') : ($quote->free ? $free : $this->money->format((string) $quote->price)),
                'info' => $option['info'],
                'html' => $option['type']->render([
                    'method' => $option['method'],
                    'quote' => $quote,
                    'selected' => $id === $this->shippingSelected,
                    'id' => $id,
                    'values' => (array) ($state['input'][$id] ?? []),
                    'info' => $option['info'],
                    'image_url' => $option['method']->image !== '' ? ImageUrl::resolve($option['method']->image, 'shipping.option') : '',
                    'errors' => $id === $this->shippingSelected ? (array) ($state['errors'] ?? []) : [],
                    'price_text' => $quote->pending ? Text::_('COM_DHARMASHOP_SHIPPING_PENDING') : $this->money->format((string) $quote->price),
                    'total_text' => $this->totalText(round((float) $this->totals['total'] + ($quote->pending ? 0.0 : $quote->price), 2)),
                    'pending' => $quote->pending,
                    'ajax_url' => Route::_('index.php?option=com_dharmashop&task=shipping.ajax&format=json', false),
                    'free_text' => $free,
                ]),
            ];
        }
    }

    /** Способы оплаты: доступность по сумме заказа (с доставкой) и выбранной доставке; HTML панели — макет плагина типа. */
    private function preparePayment($app, $settings): void
    {
        $valid = array_values(array_filter($this->lines, static fn (object $line): bool => $line->error === ''));

        if (!$valid || (!$this->shippingOptions && $this->shippingBlocked)) {
            return;
        }

        $service = $app->bootComponent('com_dharmashop')->getServices()->payments();

        if (!$service->hasMethods()) {
            return;
        }

        $map = $service->allowedByShipping();
        $context = new PaymentContext($this->totalWithShipping(), (float) $this->totals['subtotal'], (float) $this->totals['qty'], (int) $this->totals['count'], (string) $settings->get('currency_code', 'RUB'), $valid, $this->values, $this->shippingSelected);
        // Все способы, подходящие по сумме: какие из них доступны для выбранной доставки, решает карта и скрипт (при смене доставки без перезагрузки).
        $options = $service->options($context);

        if (!$options) {
            $this->paymentBlocked = true;

            return;
        }

        $this->paymentMap = (string) json_encode((object) array_map(static fn (array $ids): array => array_values($ids), $map), JSON_UNESCAPED_UNICODE);
        $allowed = (array) ($map[$this->shippingSelected] ?? []);
        $visible = $allowed === [] ? array_keys($options) : array_values(array_intersect(array_keys($options), $allowed));
        $state = (array) $app->getUserState('com_dharmashop.checkout.payment', []);
        $chosen = (int) ($state['method'] ?? 0);
        $this->paymentSelected = in_array($chosen, $visible, true) ? $chosen : $service->defaultId(array_intersect_key($options, array_flip($visible)));

        foreach ($options as $id => $option) {
            $this->paymentOptions[$id] = [
                'id' => $id,
                'selected' => $id === $this->paymentSelected,
                'visible' => in_array($id, $visible, true),
                'image_url' => $option['method']->image !== '' ? ImageUrl::resolve($option['method']->image, 'payment.option') : '',
                'title' => $option['method']->title,
                'info' => $option['info'],
                'html' => $option['type']->render([
                    'method' => $option['method'],
                    'selected' => $id === $this->paymentSelected,
                    'id' => $id,
                    'values' => (array) ($state['input'][$id] ?? []),
                    'errors' => $id === $this->paymentSelected ? (array) ($state['errors'] ?? []) : [],
                    'info' => $option['info'],
                    'image_url' => $option['method']->image !== '' ? ImageUrl::resolve($option['method']->image, 'payment.option') : '',
                    'ajax_url' => Route::_('index.php?option=com_dharmashop&task=payment.ajax&format=json', false),
                ]),
            ];
        }
    }

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $settings = ComponentHelper::getParams('com_dharmashop');

        if ((int) $settings->get('catalog_mode', 0) === 1) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_CART_ERROR_CATALOG'), 404);
        }

        $app->allowCache(false);
        $this->onepage = $this->onepage || \Dharma\Component\DharmaShop\Site\Support\CheckoutMode::oneStep() || $app->getInput()->getCmd('layout') === 'onepage';

        if ($this->onepage && $this->getLayout() === 'default') {
            $this->setLayout('onepage');
        }

        // Сохраняем старые ссылки результата, но адрес/модули берём из отдельного пункта меню.
        if ($app->getInput()->getInt('completed') === 1) {
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=checkoutcomplete',false));
            return;
        }
        $this->contacts = CheckoutFields::contacts($settings);
        $this->agreements = CheckoutFields::definitions($settings->get('checkout_consents', []));
        $this->money = new MoneyFormatter($settings);
        $this->cartUrl = \Dharma\Component\DharmaShop\Site\Support\CheckoutMode::cartUrl();
        $this->catalogUrl = Route::_('index.php?option=com_dharmashop&view=categories', false);
        $session = new CartSession($app->bootComponent('com_dharmashop')->getServices()->cart(), $app);
        $cart = $session->current();
        $identity = $app->getIdentity();
        $userId = $identity && !$identity->guest ? (int) $identity->id : 0;
        $this->loginRequired = $userId === 0 && (int) $settings->get('checkout_guest',1) !== 1;
        $this->loginUrl = Route::_('index.php?option=com_users&view=login&return=' . rawurlencode(base64_encode(Route::_('index.php?option=com_dharmashop&view=checkout',false))),false);
        $receipt = (array) $app->getUserState('com_dharmashop.checkout.receipt',[]);
        if ($app->getInput()->getInt('completed') === 1 && !empty($receipt['id']) && (int) ($receipt['user_id'] ?? -1) === $userId && (int) ($receipt['time'] ?? 0) >= time()-1800) $this->receipt = $receipt;
        $this->values = (array) $app->getUserState('com_dharmashop.checkout.values',[]);
        $this->errors = (array) $app->getUserState('com_dharmashop.checkout.errors',[]);
        $app->setUserState('com_dharmashop.checkout.errors',null);
        $pending = (array) $app->getUserState('com_dharmashop.checkout.pending',[]);
        if (isset($pending['user_id']) && (int) $pending['user_id'] !== $userId) {
            $this->values = []; $this->errors = [];
            $app->setUserState('com_dharmashop.checkout.values',null);
        }
        // Вошедшему пользователю подставляем всё, что о нём известно (поля, введённые при ошибке, не перезаписываются).
        if ($userId > 0 && !$this->values) {
            $this->values = CheckoutPrefill::forUser(Factory::getContainer()->get(DatabaseInterface::class), $identity, $this->contacts);
        }
        if ($cart && !$this->receipt && !$this->loginRequired) {
            if (empty($pending['key']) || !empty($pending['used']) || (int) ($pending['cart_id'] ?? 0) !== (int) $cart->id || (int) ($pending['user_id'] ?? -1) !== $userId) {
                $pending = ['key'=>bin2hex(random_bytes(16)),'cart_id'=>(int) $cart->id,'user_id'=>$userId,'used'=>false];
                $app->setUserState('com_dharmashop.checkout.pending',$pending);
            }
            $this->submitKey = $pending['key'];
        }

        if ($cart !== null) {
            $this->lines = $session->service()->lines($cart);
            $this->totals = $session->service()->totals($cart, $this->lines);

            foreach ($this->lines as $line) {
                $line->url = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $line->product_id, false);

                if ($this->onepage) {
                    // Фото для карточек состава корзины (макет cart/lines), как на странице корзины.
                    $line->image = \Dharma\Component\DharmaShop\Site\Support\MediaUrl::absolute((string) $line->cover);
                }
            }
        }

        if (!$this->receipt && !$this->loginRequired) {
            $this->prepareShipping($app, $settings);
            $this->preparePayment($app, $settings);
        }

        $document = $this->getDocument();

        if ($document instanceof HtmlDocument) {
            $title = trim((string) $app->getParams()->get('page_title', ''));
            $document->setTitle($title !== '' ? $title : Text::_('COM_DHARMASHOP_CHECKOUT_TITLE'));
            $document->setMetaData('robots', 'noindex, nofollow');
            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $wa->useScript('com_dharmashop.checkout');
            $document->addScriptOptions('com_dharmashop.checkout', [
                'phoneInvalid' => Text::_('COM_DHARMASHOP_CHECKOUT_PHONE_INVALID'),
                'processing' => Text::_('COM_DHARMASHOP_CHECKOUT_PROCESSING'),
            ]);

            if ($this->onepage) {
                // Состав корзины меняется без перезагрузки (cart-page.js), нижняя часть подгружается заново (checkout-onepage.js).
                $wa->useScript('com_dharmashop.cart-page')->useScript('com_dharmashop.checkout-onepage');
                \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::assets($document, $settings);
                $fragment = static fn (string $part): string => Route::_('index.php?option=com_dharmashop&view=checkout&layout=onepage&part=' . $part . '&format=raw', false);
                $document->addScriptOptions('com_dharmashop.cartpage', [
                    'fragmentUrl' => $fragment('cart'),
                    'updateUrl' => Route::_('index.php?option=com_dharmashop&task=cart.update&format=json', false),
                    'removeUrl' => Route::_('index.php?option=com_dharmashop&task=cart.remove&format=json', false),
                    'clearUrl' => Route::_('index.php?option=com_dharmashop&task=cart.clear&format=json', false),
                    'error' => Text::_('COM_DHARMASHOP_CART_ERROR_GENERIC'),
                ]);
                $document->addScriptOptions('com_dharmashop.onepage', ['formUrl' => $fragment('form')]);
            }
        }

        parent::display($tpl);
    }
}
