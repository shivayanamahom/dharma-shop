<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field\Modal;

use Dharma\Component\DharmaShop\Administrator\Catalog\ProductPicker;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Выбор товара в окне со списком (поиск, фильтры, страницы) — штатный механизм Joomla, как «Выбрать статью» в com_content.
 * Каталог может быть любого размера: список подгружается постранично, а не выпадающим списком. Значение — номер товара.
 * Подключение в форме: `addfieldprefix="Dharma\Component\DharmaShop\Administrator\Field"`, `type="modal_product"`.
 */
class ProductField extends ModalSelectField
{
    protected $type = 'Modal_Product';
    protected string $previewLayout = 'field.product-picker';
    protected string $imageContext = 'admin.picker.field';

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        Factory::getApplication()->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);

        $select = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $select->setQuery([
            'option' => 'com_dharmashop',
            'view' => 'products',
            'layout' => 'modal',
            'tmpl' => 'component',
            Session::getFormToken() => 1,
        ]);

        $this->urls['select'] = (string) $select;
        $this->modalTitles['select'] = Text::_('COM_DHARMASHOP_SELECT_A_PRODUCT');
        $this->hint = $this->hint ?: Text::_('COM_DHARMASHOP_SELECT_A_PRODUCT');

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.product-picker');

        // Опция для повторяемых форм: диалог остаётся штатным, исключения обновляются перед каждым открытием.
        if ((string) $element['unique'] === 'true') {
            $this->dataAttributes['data-dharmashop-product-picker'] = 'unique';
        }

        return $result;
    }

    protected function getInput()
    {
        $input = parent::getInput();
        $picker = new ProductPicker($this->getDatabase());
        $item = $picker->selected((int) $this->value, $this->form ? $this->form->getData()->toArray() : []);
        return (new FileLayout($this->previewLayout, JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts', ['component' => 'com_dharmashop', 'client' => 1]))
            ->render(['input' => $input, 'product' => $item ? $this->previewData($picker, $item) : null]);
    }

    protected function previewData(ProductPicker $picker, object $item): array
    {
        return $picker->presentation($item, $this->imageContext);
    }

    /** The shared batch cache also supplies the native field title. */
    protected function getValueTitle()
    {
        $item = (new ProductPicker($this->getDatabase()))->selected((int) $this->value, $this->form ? $this->form->getData()->toArray() : []);
        return $item ? $item->title : ((int) $this->value ?: '');
    }
}
