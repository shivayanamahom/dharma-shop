<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Site\Support\FavoritesSession;
use Dharma\Component\DharmaShop\Site\Support\FavoritesSettings;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/** POST-only actions for a visitor's private favorites list. */
class FavoritesController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function toggle(): void
    {
        $this->run(true);
    }

    public function remove(): void
    {
        $this->run(false);
    }

    public function clear(): void
    {
        $this->run(false, true);
    }

    private function run(bool $toggle, bool $clear = false): void
    {
        $ajax = $this->input->getCmd('format') === 'json' || strtolower((string) $this->input->server->getString('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
        $url = Route::_('index.php?option=com_dharmashop&view=favorites', false);
        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            $this->finish($ajax, ['ok' => false, 'error' => 'COM_DHARMASHOP_FAVORITES_ERROR_TOKEN'], $url, null);
            return;
        }
        if ($toggle && !FavoritesSettings::enabled()) {
            $this->finish($ajax, ['ok' => false, 'error' => 'COM_DHARMASHOP_FAVORITES_DISABLED'], $url, null);
            return;
        }
        $session = new FavoritesSession($this->app->bootComponent('com_dharmashop')->getServices()->favorites(), $this->app);
        $list = $session->current();
        $result = ['ok' => true, 'count' => 0];
        if ($clear) {
            if ($list) { $session->service()->clear($list); }
        } else {
            $productId = $this->input->post->getInt('product_id');
            if ($productId < 1) {
                $result = ['ok' => false, 'error' => 'COM_DHARMASHOP_FAVORITES_PRODUCT_UNAVAILABLE'];
            } elseif ($toggle) {
                $result = $session->service()->toggle($session->ensure(), $productId);
                $result['productId'] = $productId;
            } elseif ($list) {
                $session->service()->remove($list, $productId);
            }
        }
        $list = $session->current();
        $result['count'] = $list ? $session->service()->count((int) $list->id) : 0;
        $this->finish($ajax, $result, $url, $session);
    }

    private function finish(bool $ajax, array $result, string $url, ?FavoritesSession $session): void
    {
        $ok = !empty($result['ok']);
        $message = Text::_((string) ($result['error'] ?? 'COM_DHARMASHOP_FAVORITES_UPDATED'));
        if (!$ajax) {
            $this->setRedirect($url, $message, $ok ? 'message' : 'warning');
            return;
        }
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode(['success' => $ok, 'message' => $message, 'count' => (int) ($result['count'] ?? 0), 'added' => $result['added'] ?? null, 'productId' => $result['productId'] ?? null], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->app->close();
    }
}
