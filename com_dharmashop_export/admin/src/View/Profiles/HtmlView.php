<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\View\Profiles;
use Joomla\CMS\Toolbar\{Toolbar,ToolbarHelper};
use Dharma\Component\DharmaShopExport\Administrator\Export\Access;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_export';protected array $items=[];protected $pagination;public $filterForm;public array $activeFilters=[];
 public function display($tpl=null):void {
  Access::require('core.manage');$m=$this->getModel();$this->items=$m->getItems();$this->pagination=$m->getPagination();$this->filterForm=$m->getFilterForm();$this->activeFilters=$m->getActiveFilters();
  ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT'),'download');$t=Toolbar::getInstance();$u=$this->getCurrentUser();
  if($u->authorise('core.create',$this->option)){$t->addNew('profile.add');}
  if($u->authorise('core.edit.state',$this->option)){$t->publish('profiles.publish')->listCheck(true);$t->unpublish('profiles.unpublish')->listCheck(true);$t->checkin('profiles.checkin');}
  if($u->authorise('core.delete',$this->option)){$t->delete('profiles.delete')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);}
  if($u->authorise('dharmashop.export.report',$this->option)){$t->linkButton('history','COM_DHARMASHOP_EXPORT_HISTORY')->url('index.php?option='.$this->option.'&view=jobs')->icon('icon-history');}
  if($u->authorise('core.admin','com_dharmashop')){$t->linkButton('settings','COM_DHARMASHOP_EXPORT_SETTINGS')->url('index.php?option=com_config&view=component&component=com_dharmashop');}
  if($u->authorise('core.admin',$this->option)){ToolbarHelper::preferences($this->option);}
  parent::display($tpl);
 }
}
