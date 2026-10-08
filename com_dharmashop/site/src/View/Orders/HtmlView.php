<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Orders;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\OrdersModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Мои заказы (личный кабинет): список заказов вошедшего пользователя; гость видит приглашение войти. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var object[] */
    public array $orders = [];
    public bool $guest = true;
    public ?Pagination $pagination = null;
    public string $loginUrl = '';
    public string $catalogUrl = '';
    /** Цвета статусов, допустимые для классов Bootstrap (`text-bg-…`). */
    private const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        /** @var OrdersModel $model */
        $model = $this->getModel();
        $settings = ComponentHelper::getParams('com_dharmashop');
        $this->guest = $model->getUserId() === 0;
        $this->catalogUrl = Route::_('index.php?option=com_dharmashop&view=categories', false);
        $this->loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Uri::getInstance()->toString(['path', 'query'])), false);

        if (!$this->guest) {
            $limit = 10;
            $total = $model->getTotal();
            $start = max(0, $app->getInput()->getInt('start', $app->getInput()->getInt('limitstart')));
            $pagination = new Pagination($total, intdiv($start, $limit) * $limit, $limit);
            $this->orders = $total ? $model->getOrders($limit, $pagination->limitstart) : [];
            $this->pagination = $total > $limit ? $pagination : null;
            $money = new MoneyFormatter($settings);

            foreach ($this->orders as $order) {
                $order->date = HTMLHelper::_('date', $order->created, 'd.m.Y H:i');
                $order->amount = (string) $order->currency !== (string) $settings->get('currency_code', 'RUB')
                    ? $money->format($order->total) . ' ' . $order->currency : $money->format($order->total);
                $order->url = Route::_('index.php?option=com_dharmashop&view=order&id=' . (int) $order->id, false);
                $order->color = in_array((string) $order->status_color, self::COLORS, true) ? (string) $order->status_color : 'secondary';
            }
        }

        $document = $this->getDocument();

        if ($document instanceof HtmlDocument) {
            $document->setTitle(Text::_('COM_DHARMASHOP_MYORDERS'));
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        parent::display($tpl);
    }
}
