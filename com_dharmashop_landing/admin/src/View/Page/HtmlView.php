<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\View\Page;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop_landing';
    protected $item;
    protected $form;

    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();
        $this->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
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
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_LANDING_FORM_ERROR'), 500);
        }

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_($this->item->id ? 'COM_DHARMASHOP_LANDING_EDIT' : 'COM_DHARMASHOP_LANDING_NEW'), 'file-alt');
        $toolbar = Toolbar::getInstance();
        $toolbar->apply('page.apply');
        $toolbar->save('page.save');

        if ($user->authorise('core.create', $this->option)) {
            $toolbar->save2new('page.save2new');
        }

        $toolbar->cancel('page.cancel', 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
