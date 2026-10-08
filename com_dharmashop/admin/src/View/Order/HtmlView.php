<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\View\Order;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Form\Form;
\defined('_JEXEC') or die;
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected $item;
    public Form $statusForm;
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('dharmashop.orders.manage',$this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $this->item = $this->getModel()->getItem();
        if (!$this->item) throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'),404);
        $this->statusForm = Form::getInstance('com_dharmashop.order_status', JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/order_status.xml');
        $this->statusForm->bind(['status_id'=>(int) $this->item->status_id]);
        ToolbarHelper::title(Text::sprintf('COM_DHARMASHOP_ORDER_TITLE',$this->item->number),'list');
        parent::display($tpl);
    }
}
