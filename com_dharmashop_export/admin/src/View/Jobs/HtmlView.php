<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\View\Jobs;
use Dharma\Component\DharmaShopExport\Administrator\Export\Access;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_export';protected $items;protected $pagination;
 public function display($tpl=null):void {Access::require('dharmashop.export.report');$m=$this->getModel();$this->items=$m->getItems();$this->pagination=$m->getPagination();\Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT_HISTORY'),'history');$toolbar=\Joomla\CMS\Toolbar\Toolbar::getInstance();$toolbar->linkButton('profiles','COM_DHARMASHOP_EXPORT_PROFILES')->url('index.php?option='.$this->option.'&view=profiles')->icon('icon-list');
  if($this->getCurrentUser()->authorise('core.delete',$this->option)){
   $toolbar->delete('jobs.delete')->message('COM_DHARMASHOP_EXPORT_JOBS_DELETE_CONFIRM')->listCheck(true);
   $toolbar->confirmButton('clear','COM_DHARMASHOP_EXPORT_JOBS_CLEAR','jobs.clear')->icon('icon-trash')->message('COM_DHARMASHOP_EXPORT_JOBS_CLEAR_CONFIRM');
  }
  parent::display($tpl);}
}
