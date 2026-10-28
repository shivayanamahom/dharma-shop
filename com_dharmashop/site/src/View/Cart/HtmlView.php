<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Cart;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Страница корзины: строки, итоги, изменение количества и удаление обычными формами (без JavaScript). */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var object[] */
    public array $lines = [];
    public array $totals = ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0, 'adjustments' => [], 'total' => 0.0];
    public MoneyFormatter $money;
    public string $catalogUrl = '';
    public string $actionUrl = '';
    /** Просмотр чужой корзины администратором (только чтение, без действий и скриптов). */
    public bool $readOnly = false;
    /** @var array<string, bool|string> что показывать в мини-корзине и классы кнопок (задаёт модуль корзины) */
    public array $mini = ['image' => true, 'category' => true, 'sku' => false, 'unit' => true, 'discount' => true, 'checkout' => true, 'cart_class' => 'btn btn-outline-primary', 'checkout_class' => 'btn btn-primary'];

    /** Итог для вывода: при включённой замене нулевой цены строки без цены дописываются к сумме («19 940 ₽ + Цена по запросу»). */
    public function totalText(float $amount): string
    {
        $zero = $this->money->replacesZero()
            && array_filter($this->lines, static fn (object $line): bool => $line->error === '' && $line->unit_price !== null && $line->unit_price === 0.0 && empty($line->zero_free));

        if (!$zero) {
            // Нулевой итог без строк «по запросу» — все позиции бесплатные.
            return $this->money->price((string) $amount, true);
        }

        return $amount > 0 ? $this->money->format((string) $amount) . ' + ' . $this->money->zeroText() : $this->money->zeroText();
    }

    /**
     * Сумма позиций по обычным ценам и размер скидки (разница с итоговыми ценами) для боковой панели итога; только позиции без ошибок.
     *
     * @return  array{before: float, saved: float}
     */
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

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $settings = ComponentHelper::getParams('com_dharmashop');

        // В режиме каталога корзины нет.
        if ((int) $settings->get('catalog_mode', 0) === 1) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_CART_ERROR_CATALOG'), 404);
        }

        $app->allowCache(false);
        $this->money = new MoneyFormatter($settings);
        $session = new CartSession($app->bootComponent('com_dharmashop')->getServices()->cart(), $app);
        $cart = $session->current();

        // Просмотр корзины по секретному ключу (ссылка из раздела «Корзины» в админке): только чтение и только для администратора магазина;
        // чужой ключ без права `dharmashop.orders.manage` игнорируется. Адрес с ключом не индексируется и не передаётся в Referer.
        $key = (string) $app->getInput()->get('cart', '', 'cmd');

        if ($key !== '' && preg_match('/^[a-f0-9]{32}$/', $key) === 1 && $app->getIdentity()->authorise('dharmashop.orders.manage', 'com_dharmashop')) {
            $viewed = $session->service()->find($key, 0);

            if ($viewed !== null) {
                $cart = $viewed;
                $this->readOnly = true;
                $app->setHeader('Referrer-Policy', 'no-referrer', true);
            }
        }

        if ($cart !== null) {
            $service = $session->service();
            $this->lines = $service->lines($cart);
            $this->totals = $service->totals($cart, $this->lines);

            foreach ($this->lines as $line) {
                $line->image = MediaUrl::absolute((string) $line->cover);
                $line->url = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $line->product_id, false);
            }
        }

        $this->catalogUrl = Route::_('index.php?option=com_dharmashop&view=categories', false);
        $this->actionUrl = Route::_('index.php?option=com_dharmashop&task=cart.update', false);

        if ($this->getDocument() instanceof HtmlDocument) {
            // Количество, удаление и очистка без перезагрузки (cart-page.js); без JavaScript работают обычные формы.
            $document = $this->getDocument();
            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::assets($document, ComponentHelper::getParams('com_dharmashop'));
            if (!$this->readOnly) {
                $wa->useScript('com_dharmashop.cart-page');
            }

            $document->addScriptOptions('com_dharmashop.cartpage', [
                'fragmentUrl' => Route::_('index.php?option=com_dharmashop&view=cart&layout=page&format=raw', false),
                'updateUrl' => Route::_('index.php?option=com_dharmashop&task=cart.update&format=json', false),
                'removeUrl' => Route::_('index.php?option=com_dharmashop&task=cart.remove&format=json', false),
                'clearUrl' => Route::_('index.php?option=com_dharmashop&task=cart.clear&format=json', false),
                'error' => Text::_('COM_DHARMASHOP_CART_ERROR_GENERIC'),
            ]);

            $this->getDocument()->setTitle(Text::_('COM_DHARMASHOP_CART_TITLE'));
            $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
        }

        parent::display($tpl);
    }
}
