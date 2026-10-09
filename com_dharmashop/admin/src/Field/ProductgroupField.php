<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Joomla\CMS\Form\Field\SqlField;
\defined('_JEXEC') or die;
/** Native select; groups are definitions, products still use the paginated picker. */
class ProductgroupField extends SqlField
{
 protected $type='Productgroup';
 protected function getOptions()
 {
  $this->keyField='value';$this->valueField='text';
  $this->query='SELECT id AS value,title AS text FROM #__dharmashop_product_groups WHERE state IN (0,1) ORDER BY title,id';
  return array_merge([\Joomla\CMS\HTML\HTMLHelper::_('select.option',0,\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_GROUP_NONE'))],parent::getOptions());
 }
}
