<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Table\Category as CoreCategoryTable;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Категория магазина (любого типа) — штатная таблица категорий Joomla.
 * Отличие одно: корневые категории типов (бренды, бейджи) наследуют права компонента com_dharmashop,
 * а не корня сайта (штатная таблица ищет ресурс по имени секции, а ресурса «com_dharmashop.brand» нет).
 */
class CategoryTable extends CoreCategoryTable
{
    protected function _getAssetParentId(?Table $table = null, $id = null)
    {
        if ((int) $this->parent_id <= 1) {
            $db = $this->getDatabase();
            $component = 'com_dharmashop';
            $query = $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = :component')
                ->bind(':component', $component, ParameterType::STRING);

            if ($assetId = (int) $db->setQuery($query)->loadResult()) {
                return $assetId;
            }
        }

        return parent::_getAssetParentId($table, $id);
    }
}
