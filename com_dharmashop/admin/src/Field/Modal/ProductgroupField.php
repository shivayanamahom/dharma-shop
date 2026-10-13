<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field\Modal;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Compact native group selector; unlike the batch select it never loads the entire catalogue. */
class ProductgroupField extends ModalSelectField
{
    protected $type = 'Modal_Productgroup';

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        if (!parent::setup($element, (int) $value > 0 ? (int) $value : '', $group)) { return false; }
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
        $url = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $url->setQuery(['option' => 'com_dharmashop', 'view' => 'productgroups', 'layout' => 'modal', 'tmpl' => 'component', Session::getFormToken() => 1]);
        $this->urls['select'] = (string) $url;
        $this->modalTitles['select'] = Text::_('COM_DHARMASHOP_GROUP_PICKER_SELECT');
        $this->sql_title_table = '#__dharmashop_product_groups';
        $this->sql_title_column = 'title';
        $this->sql_title_key = 'id';
        return true;
    }

    protected function getInput()
    {
        $app = Factory::getApplication();
        $app->getDocument()->getWebAssetManager()->useScript('modal-content-select-field')->useScript('webcomponent.toolbar-button');
        $data = $this->collectLayoutData();
        $data['valueTitle'] = $this->getValueTitle();
        $data['canCreate'] = (string) $this->element['create_group'] === 'true' && $app->getIdentity()->authorise('core.create', 'com_dharmashop') && $app->getIdentity()->authorise('dharmashop.groups.manage', 'com_dharmashop');
        return (new FileLayout('field.group-picker', JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts', ['component' => 'com_dharmashop', 'client' => 1]))->render($data);
    }
}
