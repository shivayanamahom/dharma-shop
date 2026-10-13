<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
final class Access {
 public static function require(string $action):void {
  $user=Factory::getApplication()->getIdentity();
  if(!$user->authorise('core.manage','com_dharmashop_export') || !$user->authorise($action,'com_dharmashop_export')){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}
 }
 public static function job(array $job):void {
  $u=Factory::getApplication()->getIdentity();
  if(!$u->authorise('dharmashop.export.report','com_dharmashop_export') && !($u->authorise('dharmashop.export.run','com_dharmashop_export')&&(int)$job['created_by']===(int)$u->id)){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}
 }
}
