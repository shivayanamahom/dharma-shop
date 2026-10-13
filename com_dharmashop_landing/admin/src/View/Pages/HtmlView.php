<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\View\Pages;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop_landing';
    protected array $items = [];
    protected ?Pagination $pagination = null;
    public ?Form $filterForm = null;
    public array $activeFilters = [];

    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();

        if (!$user->authorise('core.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_('COM_DHARMASHOP_LANDING_PAGES_TITLE'), 'file-alt');

        if ($user->authorise('core.create', $this->option)) {
            $toolbar->addNew('page.add');
        }

        if ($user->authorise('core.edit.state', $this->option)) {
            $toolbar->publish('pages.publish')->listCheck(true);
            $toolbar->unpublish('pages.unpublish')->listCheck(true);
            $toolbar->checkin('pages.checkin');
        }

        if ($user->authorise('core.delete', $this->option)) {
            $toolbar->delete('pages.delete', 'JTOOLBAR_DELETE')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
        }

        parent::display($tpl);
    }
}
