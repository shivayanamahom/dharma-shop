<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Payment;

use Dharma\Component\DharmaShop\Administrator\Payment\PaymentService;
use Dharma\Component\DharmaShop\Site\Support\OrderDisplay;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Страница оплаты заказа на сайте (C83): макет типа оплаты (`renderPayment`) внутри страницы магазина. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    public object $item;
    public string $html = '';
    public string $orderUrl = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Referrer-Policy', 'no-referrer', true);

        // Доступ к заказу — как у страницы заказа: владелец, гость этого сеанса или секретный ключ из ссылки.
        $order = $app->bootComponent($this->option)->getMVCFactory()->createModel('Order', 'Site', ['ignore_request' => true]);
        $this->item = $order->getItem();
        $this->orderUrl = Route::_('index.php?option=com_dharmashop&view=order&id=' . (int) $this->item->id, false);
        $status = (string) ($this->item->payment_status ?? PaymentService::STATUS_NONE);

        if ($status === PaymentService::STATUS_PAID) {
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_PAYMENT_PAGE_PAID'), 'notice');
            $app->redirect($this->orderUrl);
        }

        $data = $app->bootComponent($this->option)->getServices()->payments()->pageData((int) $this->item->id);

        // Страница оплаты есть только у способов, начавших оплату на сайте (статус «ожидает», «ошибка», «отменена»).
        if ($data === null || $status === PaymentService::STATUS_NONE) {
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_PAYMENT_PAGE_NOT_AVAILABLE'), 'notice');
            $app->redirect($this->orderUrl);
        }

        $details = new OrderDisplay($this->item, ComponentHelper::getParams($this->option));
        $this->html = $data['type']->renderPayment([
            'method' => $data['method'],
            'order' => $data['order'],
            'urls' => $data['urls'],
            'info' => $data['info'],
            'status' => $status,
            'amount_text' => $details->total,
        ]);

        $title = Text::sprintf('COM_DHARMASHOP_PAYMENT_PAGE_TITLE', $this->item->number);
        $document = $this->getDocument();

        if ($document instanceof HtmlDocument) {
            $document->setTitle($title);
            $document->setMetaData('robots', 'noindex, nofollow');
            \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::purchase($this->item, $document, ComponentHelper::getParams($this->option));
        }

        parent::display($tpl);
    }
}
