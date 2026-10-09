<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class ChoiceaxesField extends ListField
{
 protected $type='Choiceaxes';
 protected function getOptions()
 {
  $out=parent::getOptions();
  $rows=$this->getDatabase()->setQuery("SELECT id,title,params FROM #__dharmashop_fields WHERE target='product' AND state=1 AND level=1 AND store='option' AND type IN ('list','color') ORDER BY ordering,id")->loadObjectList();
  foreach($rows as $row) { $p=new Registry($row->params); if((int)$p->get('variation',0) && !(int)$p->get('multiple',0)) {$out[]=HTMLHelper::_('select.option',$row->id,$row->title);} }
  return $out;
 }
}
