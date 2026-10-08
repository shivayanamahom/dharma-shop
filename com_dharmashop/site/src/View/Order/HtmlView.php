<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\View\Order;

use Dharma\Component\DharmaShop\Site\Support\OrderDisplay;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public object $item;
    public OrderDisplay $details;
    public string $catalogUrl = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $this->item = $this->getModel()->getItem();
        $this->details = new OrderDisplay($this->item, ComponentHelper::getParams($this->option));
        $this->catalogUrl = Route::_('index.php?option=com_dharmashop&view=categories', false);
        $title = Text::sprintf('COM_DHARMASHOP_ORDER_HEADING', $this->item->number);
        $document = $this->getDocument();
        if ($document instanceof HtmlDocument) {
            $document->setTitle($title);
            $document->setMetaData('robots', 'noindex, nofollow');
        }
        $active = $app->getMenu()->getActive();
        if (($active->query['view'] ?? '') !== 'order') $app->getPathway()->addItem($title);
        parent::display($tpl);
    }
}
