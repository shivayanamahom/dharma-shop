<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Model;
\defined('_JEXEC') or die;
class JobsModel extends \Joomla\CMS\MVC\Model\ListModel {
 protected $option='com_dharmashop_export';
 protected function populateState($ordering='j.id',$direction='DESC'):void {parent::populateState($ordering,$direction);}
 protected function getListQuery() {
  return $this->getDatabase()->createQuery()->select('j.id,j.profile_id,j.state,j.created,j.written,j.skipped,j.error,j.bytes,j.artifact,p.title')->from('#__dharmashop_export_jobs j')->join('LEFT','#__dharmashop_export_profiles p ON p.id=j.profile_id')->order('j.id DESC');
 }
}
