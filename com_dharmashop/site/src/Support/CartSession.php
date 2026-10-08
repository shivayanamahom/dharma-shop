<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Cart\CartService;
use Joomla\CMS\Application\CMSApplicationInterface;

\defined('_JEXEC') or die;

/**
 * Корзина текущего посетителя: пользователь Joomla или гость по токену в cookie `dharmashop_cart` (HttpOnly, SameSite=Lax).
 * Cookie создаётся только при первом добавлении товара, просмотр страниц корзин не создаёт.
 */
final class CartSession
{
    public const COOKIE = 'dharmashop_cart';

    private ?object $cart = null;

    private bool $loaded = false;

    public function __construct(private readonly CartService $service, private readonly CMSApplicationInterface $app)
    {
    }

    public function service(): CartService
    {
        return $this->service;
    }

    /** Корзина посетителя или null, если её ещё нет. */
    public function current(): ?object
    {
        if (!$this->loaded) {
            $token = (string) $this->app->getInput()->cookie->getString(self::COOKIE, '');
            $this->cart = $this->service->find($token !== '' ? $token : null, $this->userId());
            $this->loaded = true;

            // Корзина вошедшего пользователя могла быть найдена по user_id: токен в cookie обновляем, чтобы выход не терял её у гостя.
            if ($this->cart !== null && $token !== $this->cart->token && $this->userId() > 0) {
                $this->writeCookie((string) $this->cart->token);
            }
        }

        return $this->cart;
    }

    /** Корзина посетителя; при отсутствии создаётся и токен записывается в cookie. */
    public function ensure(): object
    {
        $cart = $this->current();

        if ($cart === null) {
            $cart = $this->service->create($this->userId());
            $this->cart = $cart;
            $this->writeCookie((string) $cart->token);
        }

        return $cart;
    }

    private function userId(): int
    {
        $identity = $this->app->getIdentity();

        return $identity && !$identity->guest ? (int) $identity->id : 0;
    }

    private function writeCookie(string $token): void
    {
        $this->app->getInput()->cookie->set(self::COOKIE, $token, [
            'expires' => time() + CartService::LIFETIME_DAYS * 86400,
            'path' => (string) $this->app->get('cookie_path', '/') ?: '/',
            'domain' => (string) $this->app->get('cookie_domain', ''),
            'secure' => $this->app->isSslConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
