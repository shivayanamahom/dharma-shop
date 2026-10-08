<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Shipping;

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

        if (!$user->authorise('dharmashop.shipping.manage', $this->option)) {
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
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_SHIPPING_FORM_ERROR'), 500);
        }

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_($this->item->id ? 'COM_DHARMASHOP_SHIPPING_EDIT' : 'COM_DHARMASHOP_SHIPPING_NEW'), 'truck');
        $toolbar = Toolbar::getInstance();
        $toolbar->apply('shipping.apply');
        $toolbar->save('shipping.save');

        if ($user->authorise('core.create', $this->option)) {
            $toolbar->save2new('shipping.save2new');
        }

        $toolbar->cancel('shipping.cancel', 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
