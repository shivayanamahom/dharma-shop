<?php

/**
 * Dharma Shop — поля каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected ?Form $form = null;
    protected ?object $item = null;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('dharmashop.fields.manage', 'com_dharmashop')) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->item = $model->getItem();

        if (!$this->item) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_FIELD_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=fields', false));
            return;
        }

        $this->form = $model->getForm();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $app = Factory::getApplication();
        $app->getInput()->set('hidemainmenu', true);
        $canDo = ContentHelper::getActions('com_dharmashop');
        $isNew = empty($this->item->id);
        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_($isNew ? 'COM_DHARMASHOP_FIELD_NEW' : 'COM_DHARMASHOP_FIELD_EDIT'), 'layer-group');

        if ($isNew ? $canDo->get('core.create') : $canDo->get('core.edit')) {
            $toolbar->apply('field.apply');
            $toolbar->save('field.save');

            if ($canDo->get('core.create')) {
                $toolbar->save2new('field.save2new');
            }
        }

        $toolbar->cancel('field.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
