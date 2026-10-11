<?php

/**
 * Dharma Shop — группы самостоятельных товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Productgroup;

use Joomla\CMS\Factory;
use Dharma\Component\DharmaShop\Administrator\Support\FieldGroupTabs;
use Joomla\Database\DatabaseInterface;
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
    protected array $fieldGroups = [];
    protected int $commonFieldCount = 0;

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->item = $model->getItem();

        if (!$this->item) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_PRODUCTGROUP_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=productgroups', false));
            return;
        }

        $this->form = $model->getForm();
        $this->fieldGroups = array_values(array_filter(
            FieldGroupTabs::load(Factory::getContainer()->get(DatabaseInterface::class), 'product'),
            fn (object $group): bool => (bool) $this->form->getFieldset(FieldGroupTabs::fieldset((int) $group->id))
        ));

        if ((int) $this->item->id > 0) {
            $data = $this->form->getData()->toArray();
            $this->commonFieldCount = count((new \Dharma\Component\DharmaShop\Administrator\Fields\FieldValues(
                Factory::getContainer()->get(DatabaseInterface::class), 'group', array_map('intval', (array) ($data['axes'] ?? []))
            ))->values('product', $data));
        }

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $app = Factory::getApplication();
        $app->getInput()->set('hidemainmenu', true);
        $canDo = ContentHelper::getActions('com_dharmashop');
        $isNew = empty($this->item->id);
        $toolbar = Toolbar::getInstance();
        ToolbarHelper::title(Text::_($isNew ? 'COM_DHARMASHOP_PRODUCTGROUP_NEW' : 'COM_DHARMASHOP_PRODUCTGROUP_EDIT'), 'layer-group');

        if ($isNew ? $canDo->get('core.create') : $canDo->get('core.edit')) {
            $toolbar->apply('productgroup.apply');
            $toolbar->save('productgroup.save');

            if ($canDo->get('core.create')) {
                $toolbar->save2new('productgroup.save2new');
            }
        }

        $toolbar->cancel('productgroup.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
