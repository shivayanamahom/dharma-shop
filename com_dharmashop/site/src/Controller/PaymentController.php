<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Payment\Context;
use Dharma\Component\DharmaShop\Administrator\Payment\Outcome;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Обратные вызовы платёжных типов (C83): уведомление сервера шлюза (`notify`) и возврат покупателя (`result`, `return` и др.).
 * Подлинность проверяет тип (подпись, секрет шлюза), а не токен формы Joomla: шлюз его не знает. Ядро применяет итог к заказу.
 */
class PaymentController extends BaseController
{
    protected $option = 'com_dharmashop';

    /** Динамический запрос типа оплаты с оформления (если типу нужен): токен формы, ограничение числа запросов, ответ JSON. */
    public function ajax(): void
    {
        $result = ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_CART_ERROR_TOKEN')];

        if ($this->input->getMethod() === 'POST' && Session::checkToken('post')) {
            $session = $this->app->getSession();
            $now = time();
            $state = array_values(array_filter((array) $session->get('com_dharmashop.payment.ajax', []), static fn ($time): bool => (int) $time > $now - 300));

            if (count($state) >= 80 || !\Dharma\Component\DharmaShop\Site\Support\RateLimiter::allow('payment-ajax', \Dharma\Component\DharmaShop\Site\Support\RateLimiter::clientAddress(), 240, 300)) {
                $result = ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_SHIPPING_AJAX_LIMIT')];
            } else {
                $state[] = $now;
                $session->set('com_dharmashop.payment.ajax', $state);
                $settings = ComponentHelper::getParams($this->option);
                $shop = $this->app->bootComponent($this->option)->getServices();
                $cart = (new CartSession($shop->cart(), $this->app))->current();
                $lines = $cart ? array_values(array_filter($shop->cart()->lines($cart), static fn (object $line): bool => $line->error === '')) : [];

                if (!$lines) {
                    $result = ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_CART_EMPTY')];
                } else {
                    try {
                        $totals = $shop->cart()->totals($cart, $lines);
                        $context = new Context((float) $totals['total'], (float) $totals['subtotal'], (float) $totals['qty'], (int) $totals['count'], (string) $settings->get('currency_code', 'RUB'), $lines, (array) $this->input->post->get('contacts', [], 'array'), $this->input->post->getInt('shipping_id'));
                        $result = $shop->payments()->ajax($this->input->post->getInt('method_id'), $context, $this->input->post->getCmd('action'), (array) $this->input->post->get('data', [], 'array'));
                    } catch (\Throwable $error) {
                        Log::add('Payment ajax failed: ' . get_class($error), Log::WARNING, 'com_dharmashop');
                        $result = ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_SHIPPING_AJAX_FAILED')];
                    }
                }
            }
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true); $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }

    public function callback(): void
    {
        $methodId = $this->input->getInt('method');
        $orderId = $this->input->getInt('order');
        $action = $this->input->getCmd('action');

        // Параметры запроса (GET и POST) — только строки; разбор и проверка — в типе.
        $params = [];

        foreach ((array) $this->input->getArray() as $name => $value) {
            if (is_scalar($value) && !in_array($name, ['option', 'task', 'method', 'order', 'action'], true)) {
                $params[(string) $name] = (string) $value;
            }
        }

        $shop = $this->app->bootComponent($this->option)->getServices();
        $outcome = $shop->payments()->handle($methodId, $orderId, $action, $params);

        // Серверу шлюза отвечаем текстом и кодом; покупателя возвращаем на страницу заказа с сообщением.
        if ($action === 'notify' || $outcome->status === Outcome::IGNORED && $outcome->redirect === '') {
            $this->app->setHeader('Status', (string) $outcome->httpCode, true);
            $this->app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            $this->app->setHeader('Cache-Control', 'no-store', true);
            $this->app->sendHeaders();
            echo $outcome->body;
            $this->app->close();
        }

        $message = match ($outcome->status) {
            Outcome::PAID => ['COM_DHARMASHOP_PAYMENT_RESULT_PAID', 'message'],
            Outcome::FAILED => ['COM_DHARMASHOP_PAYMENT_RESULT_FAILED', 'warning'],
            Outcome::CANCELLED => ['COM_DHARMASHOP_PAYMENT_RESULT_CANCELLED', 'notice'],
            default => ['', ''],
        };
        $target = $outcome->redirect !== '' && preg_match('#^https?://#i', $outcome->redirect)
            ? $outcome->redirect
            : Route::_('index.php?option=com_dharmashop&view=order&id=' . $orderId, false);

        if ($message[0] !== '') {
            $this->app->enqueueMessage(Text::_($message[0]), $message[1]);
        }

        $this->app->redirect($target);
    }
}
