<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\View\Checkoutcomplete;

use Dharma\Component\DharmaShop\Site\Support\OrderDisplay;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
\defined('_JEXEC') or die;

/** Результат оформления из своей сессии и краткий сохранённый снимок заказа. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public ?array $receipt = null;
    public string $cartUrl = '';
    public string $catalogUrl = '';
    public string $orderUrl = '';
    public ?object $order = null;
    public ?OrderDisplay $details = null;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if ((int) ComponentHelper::getParams($this->option)->get('catalog_mode',0) === 1) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_CART_ERROR_CATALOG'),404);
        }
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $identity = $app->getIdentity();
        $userId = $identity && !$identity->guest ? (int) $identity->id : 0;
        $receipt = (array) $app->getUserState('com_dharmashop.checkout.receipt',[]);
        if (!empty($receipt['id']) && !empty($receipt['number'])
            && (int) ($receipt['user_id'] ?? -1) === $userId
            && (int) ($receipt['time'] ?? 0) >= time()-1800) {
            $model = $app->bootComponent($this->option)->getMVCFactory()->createModel('Order', 'Site', ['ignore_request' => true]);
            try {
                $this->order = $model->getItem((int) $receipt['id']);
                $this->receipt = $receipt;
                $this->details = new OrderDisplay($this->order, ComponentHelper::getParams($this->option));
                $this->orderUrl = Route::_('index.php?option=com_dharmashop&view=order&id=' . (int) $this->order->id, false);
            } catch (\RuntimeException $error) {
                if ($error->getCode() !== 404) throw $error;
            }
        }
        $this->cartUrl = Route::_('index.php?option=com_dharmashop&view=cart',false);
        $this->catalogUrl = Route::_('index.php?option=com_dharmashop&view=categories', false);
        $document = $this->getDocument();
        if ($document instanceof HtmlDocument) {
            $title = trim((string) $app->getParams()->get('page_title',''));
            $document->setTitle($title !== '' ? $title : Text::_('COM_DHARMASHOP_CHECKOUT_COMPLETE_TITLE'));
            $document->setMetaData('robots','noindex, nofollow');
            if ($this->order !== null) {
                \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::purchase($this->order, $document, ComponentHelper::getParams($this->option));
            }
        }
        parent::display($tpl);
    }
}
