<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Действия с корзиной: add, update, remove, clear. Метод POST и токен формы обязательны. Запрос AJAX (format=json или заголовок
 * X-Requested-With) получает JSON {success, message, cart}, обычная форма — перенаправление на страницу корзины с сообщением.
 * В режиме каталога корзины нет: все действия отклоняются.
 */
class CartController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function add(): void
    {
        $this->run(function (CartSession $session): array {
            $result = $session->service()->add(
                $session->ensure(),
                $this->input->post->getInt('product_id'),
                $this->input->post->getInt('variant_id') ?: null,
                (float) str_replace(',', '.', (string) $this->input->post->getString('qty', '1'))
            );

            return $result + ['message' => 'COM_DHARMASHOP_CART_ADDED'];
        });
    }

    /** Новые количества: массив qty[<id строки>]. */
    public function update(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();
            $quantities = (array) $this->input->post->get('qty', [], 'array');
            $error = null;

            foreach ($cart === null ? [] : $quantities as $itemId => $qty) {
                $result = $session->service()->setQty($cart, (int) $itemId, (float) str_replace(',', '.', (string) $qty));

                if (!$result['ok'] && $error === null) {
                    $error = $result['error'] ?? 'COM_DHARMASHOP_CART_ERROR_LINE';
                }
            }

            return $error === null ? ['ok' => true, 'message' => 'COM_DHARMASHOP_CART_UPDATED'] : ['ok' => false, 'error' => $error];
        });
    }

    public function remove(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();
            $removed = $cart !== null && $session->service()->remove($cart, $this->input->post->getInt('item_id'));

            return $removed ? ['ok' => true, 'message' => 'COM_DHARMASHOP_CART_REMOVED'] : ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_LINE'];
        });
    }

    public function clear(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();

            if ($cart !== null) {
                $session->service()->clear($cart);
            }

            return ['ok' => true, 'message' => 'COM_DHARMASHOP_CART_CLEARED'];
        });
    }

    /** @param callable(CartSession): array{ok: bool, error?: string, message?: string} $action */
    private function run(callable $action): void
    {
        $settings = ComponentHelper::getParams('com_dharmashop');
        $ajax = $this->input->getCmd('format') === 'json' || strtolower((string) $this->input->server->getString('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
        $cartUrl = Route::_('index.php?option=com_dharmashop&view=cart', false);

        if ((int) $settings->get('catalog_mode', 0) === 1) {
            $this->finish($ajax, ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_CATALOG'], $cartUrl, null);

            return;
        }

        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            $this->finish($ajax, ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_TOKEN'], $cartUrl, null);

            return;
        }

        $session = new CartSession($this->app->bootComponent('com_dharmashop')->getServices()->cart(), $this->app);
        $result = $action($session);
        $this->finish($ajax, $result, $cartUrl, $session);
    }

    private function finish(bool $ajax, array $result, string $cartUrl, ?CartSession $session): void
    {
        $ok = !empty($result['ok']);
        $message = Text::_($ok ? (string) ($result['message'] ?? 'COM_DHARMASHOP_CART_UPDATED') : (string) ($result['error'] ?? 'COM_DHARMASHOP_CART_ERROR_LINE'));

        if (!$ajax) {
            $this->setRedirect($cartUrl, $message, $ok ? 'message' : 'warning');

            return;
        }

        $summary = ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0];
        $cart = $session?->current();

        if ($cart !== null) {
            $summary = $session->service()->summary($cart);
        }

        $money = new MoneyFormatter(ComponentHelper::getParams('com_dharmashop'));
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        echo json_encode([
            'success' => $ok,
            'message' => $message,
            'cart' => $summary + ['subtotalText' => $money->format((string) $summary['subtotal']), 'url' => $cartUrl],
        ], JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
