<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\ShippingContextFactory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Динамические запросы типов доставки с оформления (подсказки адреса, расчёт по адресу): POST `task=shipping.ajax` с токеном Joomla,
 * `method_id`, `action` и данными `data[...]`. Контекст берётся из серверной корзины; ответ — JSON, содержимое определяет тип доставки.
 * Число запросов ограничено на сессию.
 */
class ShippingController extends BaseController
{
    protected $option = 'com_dharmashop';

    private const LIMIT = 80;

    private const WINDOW = 300;

    public function ajax(): void
    {
        $this->respond($this->handle());
    }

    /** @return array<string, mixed> */
    private function handle(): array
    {
        $settings = ComponentHelper::getParams($this->option);

        if ((int) $settings->get('catalog_mode', 0) === 1) {
            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_CART_ERROR_CATALOG')];
        }

        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_CART_ERROR_TOKEN')];
        }

        $session = $this->app->getSession();
        $state = (array) $session->get('com_dharmashop.shipping.ajax', []);
        $now = time();
        $state = array_values(array_filter($state, static fn ($time): bool => (int) $time > $now - self::WINDOW));

        // Лимит по адресу клиента дополняет лимит по сессии: сессию можно сбросить сменой cookie, а геокодер и маршруты платные.
        if (count($state) >= self::LIMIT || !\Dharma\Component\DharmaShop\Site\Support\RateLimiter::allow('shipping-ajax', \Dharma\Component\DharmaShop\Site\Support\RateLimiter::clientAddress(), self::LIMIT * 3, self::WINDOW)) {
            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_SHIPPING_AJAX_LIMIT')];
        }

        $state[] = $now;
        $session->set('com_dharmashop.shipping.ajax', $state);

        $shop = $this->app->bootComponent($this->option)->getServices();
        $context = ShippingContextFactory::forCart(new CartSession($shop->cart(), $this->app), $settings, $this->input->post->get('contacts', [], 'array'));

        if ($context === null) {
            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_CART_EMPTY')];
        }

        try {
            return $shop->shipping()->ajax(
                $this->input->post->getInt('method_id'),
                $context,
                $this->input->post->getCmd('action'),
                (array) $this->input->post->get('data', [], 'array')
            );
        } catch (\Throwable $error) {
            Log::add('Shipping ajax failed: ' . get_class($error), Log::WARNING, 'com_dharmashop');

            return ['ok' => false, 'error' => Text::_('COM_DHARMASHOP_SHIPPING_AJAX_FAILED')];
        }
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true); $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}
