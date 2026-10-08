<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Cart;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Component\ComponentHelper;
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
        $this->getDocument()->setTitle(Text::_('COM_DHARMASHOP_CART_TITLE'));
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
