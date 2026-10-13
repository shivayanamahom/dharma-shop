<?php
/** Dharma Shop — импорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopImport\Administrator\View\Profile;
use Joomla\CMS\Toolbar\{Toolbar,ToolbarHelper};
use Dharma\Component\DharmaShopImport\Administrator\Import\Access;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_import';protected $item;protected $form;
 public function display($tpl=null):void {
  $m=$this->getModel();$this->item=$m->getItem();if($this->item===false){throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_IMPORT_NOT_FOUND'),404);}Access::require(empty($this->item->id)?'core.create':'core.edit');$this->form=$m->getForm();
  if(!$this->form){throw new \RuntimeException(implode('; ',$m->getErrors()));}
  ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_IMPORT_PROFILE'),'upload');$t=Toolbar::getInstance();$t->apply('profile.apply');$t->save('profile.save');
  if($this->getCurrentUser()->authorise('dharmashop.import.check',$this->option)){$t->save('profile.saveandcheck','COM_DHARMASHOP_IMPORT_SAVE_CHECK');}
  $t->cancel('profile.cancel');parent::display($tpl);
 }
}
