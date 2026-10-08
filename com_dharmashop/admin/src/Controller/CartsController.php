<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** Действия раздела «Корзины»: удалить выбранные, очистить просроченные. Токен формы и право на удаление обязательны. */
class CartsController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function delete(): void
    {
        $this->guard();
        $ids = array_values(array_filter(array_map('intval', (array) $this->input->post->get('cid', [], 'array'))));
        $count = $ids ? $this->app->bootComponent('com_dharmashop')->getServices()->cart()->deleteCarts($ids) : 0;
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=carts', false), Text::sprintf('COM_DHARMASHOP_CARTS_DELETED', $count));
    }

    public function purge(): void
    {
        $this->guard();
        $count = $this->app->bootComponent('com_dharmashop')->getServices()->cart()->purgeExpired();
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=carts', false), Text::sprintf('COM_DHARMASHOP_CARTS_PURGED', $count));
    }

    private function guard(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_dharmashop') || !$this->app->getIdentity()->authorise('dharmashop.orders.manage', 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
