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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Выбор товара в окне со списком (поиск, фильтры, страницы) — штатный механизм Joomla, как «Выбрать статью» в com_content.
 * Каталог может быть любого размера: список подгружается постранично, а не выпадающим списком. Значение — номер товара.
 * Подключение в форме: `addfieldprefix="Dharma\Component\DharmaShop\Administrator\Field"`, `type="modal_product"`.
 */
class ProductField extends ModalSelectField
{
    protected $type = 'Modal_Product';

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

        // Опция для повторяемых форм: диалог остаётся штатным, исключения обновляются перед каждым открытием.
        if ((string) $element['unique'] === 'true') {
            $this->dataAttributes['data-dharmashop-product-picker'] = 'unique';
            $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $wa->useScript('com_dharmashop.product-picker');
        }

        return $result;
    }

    /** Название выбранного товара для поля (если товара уже нет — его номер). */
    protected function getValueTitle()
    {
        $value = (int) $this->value ?: 0;
        $title = '';

        if ($value) {
            $db = $this->getDatabase();
            $query = $db->createQuery()->select($db->quoteName('title'))->from($db->quoteName('#__dharmashop_products'))
                ->where($db->quoteName('id') . ' = :value')->bind(':value', $value, ParameterType::INTEGER);

            try {
                $title = (string) $db->setQuery($query)->loadResult();
            } catch (\Throwable $error) {
                Factory::getApplication()->enqueueMessage($error->getMessage(), 'error');
            }
        }

        return $title !== '' ? $title : ($value ?: '');
    }
}
