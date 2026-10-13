<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Controller;
\defined('_JEXEC') or die;
class ProfilesController extends \Joomla\CMS\MVC\Controller\AdminController {
 use RequiresActionTrait;
 protected function requiredActions():array {return ['*'=>'core.manage'];}
 protected $option='com_dharmashop_export';protected $text_prefix='COM_DHARMASHOP_EXPORT';
 public function getModel($name='Profile',$prefix='Administrator',$config=['ignore_request'=>true]){return parent::getModel($name,$prefix,$config);}
}
