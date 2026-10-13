<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Dispatcher;
\defined('_JEXEC') or die;
class Dispatcher extends \Joomla\CMS\Dispatcher\ComponentDispatcher {
 protected $option='com_dharmashop_export';
 protected function checkAccess():void {
  if(!$this->app->getIdentity()->authorise('core.manage',$this->option)){throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'),403);}
 }
}
