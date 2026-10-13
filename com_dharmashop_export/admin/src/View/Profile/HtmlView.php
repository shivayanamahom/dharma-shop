<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\View\Profile;
use Joomla\CMS\Toolbar\{Toolbar,ToolbarHelper};
use Dharma\Component\DharmaShopExport\Administrator\Export\Access;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_export';protected $item;protected $form;
 public function display($tpl=null):void {
  $m=$this->getModel();$this->item=$m->getItem();if($this->item===false){throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_NO_ITEMS_SELECTED'),404);}Access::require(empty($this->item->id)?'core.create':'core.edit');$this->form=$m->getForm();
  if(!$this->form){throw new \RuntimeException(implode('; ',$m->getErrors()));}
  ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT_PROFILE'),'download');$t=Toolbar::getInstance();$t->apply('profile.apply');$t->save('profile.save');
  if($this->getCurrentUser()->authorise('dharmashop.export.run',$this->option)){$t->save('profile.saveandrun','COM_DHARMASHOP_EXPORT_SAVE_RUN');}
  $t->cancel('profile.cancel');parent::display($tpl);
 }
}
