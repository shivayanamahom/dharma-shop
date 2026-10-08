<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\View\Customer;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected $form;
    protected $item;
    protected array $orders = [];
    protected string $username = '';
    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();
        if (!$user->authorise('dharmashop.customers.view', $this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        $model = $this->getModel();
        $this->item = $model->getItem();
        if (!$this->item) throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
        if (!$this->item->id && !$user->authorise('core.create', $this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        $this->form = $model->getForm();
        if (!$this->form) throw new \RuntimeException(Text::_('COM_DHARMASHOP_CUSTOMER_FORM_ERROR'), 500);
        $this->orders = $model->recentOrders((int) $this->item->id);
        $this->username = $model->userName((int) $this->item->user_id);
        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_CUSTOMER_TITLE'), 'user');
        $toolbar = Toolbar::getInstance();
        if ($user->authorise($this->item->id ? 'core.edit' : 'core.create', $this->option)) {
            $toolbar->apply('customer.apply'); $toolbar->save('customer.save');
        }
        $toolbar->cancel('customer.cancel', 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
