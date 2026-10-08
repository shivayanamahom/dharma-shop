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
    /** @var array<int, array{title: string, term: string, price_text: string, info: array, html: string}> варианты доставки: id способа => сведения для боковой панели и готовый HTML варианта (макет плагина типа) */
    public array $shippingOptions = [];
    public int $shippingSelected = 0;
    public float $shippingPrice = 0.0;
    /** Способы доставки включены, но для этой корзины ни один не подходит: заказ оформить нельзя. */
    public bool $shippingBlocked = false;

    public function totalText(float $amount): string
    {
        $zero = $this->money->replacesZero()
            && array_filter($this->lines, static fn (object $line): bool => $line->error === '' && $line->unit_price === 0.0);

        return $zero
            ? ($amount > 0 ? $this->money->format((string) $amount) . ' + ' : '') . $this->money->zeroText()
            : $this->money->price((string) $amount);
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
                $this->shippingPrice = $quote->price;
            }

            $this->shippingOptions[$id] = [
                'title' => $option['method']->title,
                'term' => $quote->term,
                'price_text' => $quote->free ? $free : $this->money->format((string) $quote->price),
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
                    'price_text' => $this->money->format((string) $quote->price),
                    'total_text' => $this->totalText(round((float) $this->totals['total'] + $quote->price, 2)),
                    'free_text' => $free,
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
        // Сохраняем старые ссылки результата, но адрес/модули берём из отдельного пункта меню.
        if ($app->getInput()->getInt('completed') === 1) {
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=checkoutcomplete',false));
            return;
        }
        $this->contacts = CheckoutFields::contacts($settings);
        $this->agreements = CheckoutFields::definitions($settings->get('checkout_consents', []));
        $this->money = new MoneyFormatter($settings);
        $this->cartUrl = Route::_('index.php?option=com_dharmashop&view=cart', false);
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
            }
        }

        if (!$this->receipt && !$this->loginRequired) {
            $this->prepareShipping($app, $settings);
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
        }

        parent::display($tpl);
    }
}
