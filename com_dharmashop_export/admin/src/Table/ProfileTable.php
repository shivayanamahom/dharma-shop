<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Table;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
\defined('_JEXEC') or die;
class ProfileTable extends Table {
 protected $_supportNullValue=true;
 protected $_jsonEncode=['params'];
 public function __construct(DatabaseInterface $db,?DispatcherInterface $dispatcher=null) {parent::__construct('#__dharmashop_export_profiles','id',$db,$dispatcher);$this->setColumnAlias('published','state');}
 public function check():bool {
  $this->title=trim(strip_tags((string)$this->title));
  if($this->title===''){ $this->setError(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT_INVALID'));return false; }return true;
 }
}
