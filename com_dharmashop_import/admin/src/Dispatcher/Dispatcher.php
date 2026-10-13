<?php
/** Dharma Shop — импорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopImport\Administrator\Dispatcher;
\defined('_JEXEC') or die;
class Dispatcher extends \Joomla\CMS\Dispatcher\ComponentDispatcher {
 protected $option='com_dharmashop_import';
 protected function checkAccess():void {
  if(!$this->app->getIdentity()->authorise('core.manage',$this->option)){throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'),403);}
 }
}
