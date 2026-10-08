<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Carts;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/** Раздел «Корзины»: список корзин гостей и пользователей, только просмотр; удаление выбранных и очистка просроченных. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected array $items = [];
    protected $pagination;
    public $filterForm;
    public array $activeFilters = [];
    public MoneyFormatter $money;
    public bool $canManage = false;

    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();

        if (!$user->authorise('dharmashop.orders.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->money = new MoneyFormatter(ComponentHelper::getParams($this->option));
        $this->canManage = $user->authorise('core.delete', $this->option);

        if ($errors = $model->getErrors()) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_MENU_CARTS'), 'cart');

        if ($this->canManage) {
            $toolbar = Toolbar::getInstance();
            $toolbar->delete('carts.delete', 'JTOOLBAR_DELETE')->message('COM_DHARMASHOP_CARTS_DELETE_CONFIRM')->listCheck(true);
            $toolbar->standardButton('purge', 'COM_DHARMASHOP_CARTS_PURGE', 'carts.purge')->icon('icon-trash')->listCheck(false);
        }

        parent::display($tpl);
    }
}
