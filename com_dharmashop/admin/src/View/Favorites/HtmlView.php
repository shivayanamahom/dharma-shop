<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Favorites;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected array $items = [];
    protected $pagination;
    public string $search = '';

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('dharmashop.favorites.view', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->search = (string) $model->getState('filter.search');
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_MENU_FAVORITES'), 'heart');
        parent::display($tpl);
    }
}
