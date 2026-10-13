<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Favorites\FavoritesService;
use Joomla\CMS\Application\CMSApplicationInterface;

\defined('_JEXEC') or die;

/** Visitor identity for favorites: Joomla user ID or a random first-party HttpOnly cookie token. */
final class FavoritesSession
{
    public const COOKIE = 'dharmashop_favorites';
    private ?object $list = null;
    private bool $loaded = false;
    private string $token = '';

    public function __construct(private readonly FavoritesService $service, private readonly CMSApplicationInterface $app)
    {
    }

    public function service(): FavoritesService { return $this->service; }

    public function current(): ?object
    {
        if (!$this->loaded) {
            $token = (string) $this->app->getInput()->cookie->getString(self::COOKIE, '');
            $user = $this->userId();
            // Cookie гостю ставится только когда создаётся список (`ensure`): страницы без действий с избранным не получают Set-Cookie
            // (иначе они не кэшируются публично).
            if ($user === 0 && !preg_match('/^[a-f0-9]{64}$/', $token)) {
                $token = bin2hex(random_bytes(32));
            }
            $this->token = $token;
            $this->list = $this->service->find($token, $user);
            $this->loaded = true;
        }
        return $this->list;
    }

    public function ensure(): object
    {
        if ($list = $this->current()) { return $list; }
        $this->list = $this->service->ensure($this->token, $this->userId());
        $this->loaded = true;
        if (!empty($this->list->newToken)) { $this->writeCookie((string) $this->list->newToken); }
        elseif ($this->userId() === 0 && !preg_match('/^[a-f0-9]{64}$/', (string) $this->app->getInput()->cookie->getString(self::COOKIE, ''))) { $this->writeCookie($this->token); }
        return $this->list;
    }

    private function userId(): int
    {
        $identity = $this->app->getIdentity();
        return $identity && !$identity->guest ? (int) $identity->id : 0;
    }

    private function writeCookie(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return; }
        $this->app->getInput()->cookie->set(self::COOKIE, $token, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => (string) $this->app->get('cookie_path', '/') ?: '/',
            'domain' => (string) $this->app->get('cookie_domain', ''),
            'secure' => $this->app->isSslConnection(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
