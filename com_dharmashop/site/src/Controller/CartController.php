<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Cart\CartService;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\RateLimiter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Действия с корзиной: add, update, remove, clear. Метод POST и токен формы обязательны. Запрос AJAX (format=json или заголовок
 * X-Requested-With) получает JSON {success, message, cart, notification}, обычная форма — перенаправление на страницу корзины с сообщением.
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

    /**
     * Исправляет корзину с недоступными позициями: `mode=remove` — удаляет их, `mode=reduce` — уменьшает позиции, не хватающие остатка,
     * до наличия (остальные недоступные удаляются). `return=checkout` возвращает на оформление, иначе — на страницу корзины.
     */
    public function removeUnavailable(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();

            if ($cart === null) {
                return ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_ERROR_LINE'];
            }

            $service = $session->service();
            $reduce = $this->input->post->getCmd('mode') === 'reduce';
            $changed = 0;

            foreach ($service->lines($cart) as $line) {
                if ($line->error === '') {
                    continue;
                }

                $qty = $reduce ? CartService::reducedQty($line) : null;
                $done = $qty !== null ? ($service->setQty($cart, (int) $line->id, $qty)['ok'] ?? false) : $service->remove($cart, (int) $line->id);
                $changed += $done ? 1 : 0;
            }

            return $changed > 0
                ? ['ok' => true, 'message' => $reduce ? 'COM_DHARMASHOP_CART_REDUCED' : 'COM_DHARMASHOP_CART_UNAVAILABLE_REMOVED']
                : ['ok' => false, 'error' => 'COM_DHARMASHOP_CART_NOTHING_TO_FIX'];
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

    public function applyPromotion(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();
            if ($cart === null) return ['ok'=>false,'error'=>'COM_DHARMASHOP_CART_EMPTY'];
            if (!RateLimiter::allow('promotion-code',RateLimiter::clientAddress(),30,3600)) return ['ok'=>false,'error'=>'COM_DHARMASHOP_PROMOTION_RATE_LIMIT'];
            try { $this->app->bootComponent('com_dharmashop')->getServices()->promotions()->applyCode($cart,$this->input->post->getString('promotion_code')); }
            catch (\DomainException $error) { return ['ok'=>false,'error'=>$error->getMessage()]; }
            $totals = $session->service()->totals($cart,$session->service()->lines($cart));
            return ['ok'=>true,'message'=>!empty($totals['promotion_inactive']) ? 'COM_DHARMASHOP_PROMOTION_APPLIED_WAITING' : (!empty($totals['promotion_pending_identity']) ? 'COM_DHARMASHOP_PROMOTION_APPLIED_PENDING' : 'COM_DHARMASHOP_PROMOTION_APPLIED')];
        });
    }

    public function removePromotion(): void
    {
        $this->run(function (CartSession $session): array {
            $cart = $session->current();
            if ($cart !== null) $this->app->bootComponent('com_dharmashop')->getServices()->promotions()->removeCode($cart);
            return ['ok'=>true,'message'=>'COM_DHARMASHOP_PROMOTION_REMOVED'];
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
        // Электронная коммерция (C98): по разнице состава корзины до и после действия; выключена — без лишних запросов.
        $track = \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::enabled($settings) && $ajax;
        $before = $track ? \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::cartSnapshot($session->current()) : [];
        $result = $action($session);
        $ecommerce = $track && !empty($result['ok']) ? \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::cartChanges($before, \Dharma\Component\DharmaShop\Site\Analytics\Ecommerce::cartSnapshot($session->current()), $settings) : null;
        $this->finish($ajax, $result, $cartUrl, $session, $ecommerce);
    }

    private function finish(bool $ajax, array $result, string $cartUrl, ?CartSession $session, ?array $ecommerce = null): void
    {
        $ok = !empty($result['ok']);
        $message = Text::_($ok ? (string) ($result['message'] ?? 'COM_DHARMASHOP_CART_UPDATED') : (string) ($result['error'] ?? 'COM_DHARMASHOP_CART_ERROR_LINE'));

        if (!$ajax) {
            // Возврат на оформление допускается только по белому списку (`return=checkout`), иначе — страница корзины.
        $target = $this->input->post->getCmd('return') === 'checkout' ? Route::_('index.php?option=com_dharmashop&view=checkout', false) : $cartUrl;
        $this->setRedirect($target, $message, $ok ? 'message' : 'warning');

            return;
        }

        $summary = ['count' => 0, 'qty' => 0.0, 'subtotal' => 0.0, 'total' => 0.0];
        $products = [];
        $cart = $session?->current();

        if ($cart !== null) {
            $cartService = $session->service();
            $summary = $cartService->summary($cart);
            $cartTotals = $cartService->totals($cart, $cartService->lines($cart));
            $summary['total'] = (float) $cartTotals['total'];
            $products = $cartService->productQuantities($cart);
        }

        $settings = ComponentHelper::getParams('com_dharmashop');
        $notification = null;

        // Только успешное добавление: обновление количества и ошибки не создают тост.
        if ($ok && isset($result['added']) && $settings->get('cart_notification_type', 'toast') === 'toast') {
            try {
                $notification = ['type' => 'toast', 'html' => (new FileLayout('components.dharmashop.cart.toast', null,
                    ['component' => 'none', 'client' => 0]))->render([
                        'product' => $result['added'], 'cartUrl' => $cartUrl,
                        'seconds' => max(0, min(300, (int) $settings->get('cart_notification_seconds', 5))),
                    ])];
            } catch (\Throwable $error) {
                // Ошибка переопределённого макета не должна превращать уже выполненное добавление в ошибку покупки.
                Log::add('Cart toast: ' . get_class($error), Log::WARNING, 'com_dharmashop');
            }
        }

        $money = new MoneyFormatter($settings);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true); $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        echo json_encode([
            'success' => $ok,
            'message' => $message,
            'notification' => $notification,
            'cart' => $summary + [
                'subtotalText' => (int) $summary['count'] > 0 ? $money->price((string) $summary['subtotal']) : $money->format((string) $summary['subtotal']),
                'totalText' => (int) $summary['count'] > 0 ? $money->price((string) $summary['total']) : $money->format((string) $summary['total']),
                'url' => $cartUrl,
                'products' => (object) $products,
            ] + ($ecommerce !== null ? ['ecommerce' => $ecommerce] : []),
        ], JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
