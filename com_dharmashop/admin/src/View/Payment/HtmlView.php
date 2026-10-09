<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Payment;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected $item;
    protected $form;

    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();

        if (!$user->authorise('dharmashop.payment.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->item = $model->getItem();

        if (!$this->item) {
            throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
        }

        if (!$user->authorise($this->item->id ? 'core.edit' : 'core.create', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->form = $model->getForm();

        if (!$this->form) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_PAYMENT_FORM_ERROR'), 500);
        }

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_($this->item->id ? 'COM_DHARMASHOP_PAYMENT_EDIT' : 'COM_DHARMASHOP_PAYMENT_NEW'), 'credit-card');
        $toolbar = Toolbar::getInstance();
        $toolbar->apply('payment.apply');
        $toolbar->save('payment.save');

        if ($user->authorise('core.create', $this->option)) {
            $toolbar->save2new('payment.save2new');
        }

        $toolbar->cancel('payment.cancel', 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
