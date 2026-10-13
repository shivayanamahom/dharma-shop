<?php
/** Dharma Shop — импорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopImport\Administrator\Controller;
\defined('_JEXEC') or die;
class ProfilesController extends \Joomla\CMS\MVC\Controller\AdminController {
 use RequiresActionTrait;
 protected function requiredActions():array {return ['*'=>'core.manage'];}
 protected $option='com_dharmashop_import';protected $text_prefix='COM_DHARMASHOP_IMPORT';
 public function getModel($name='Profile',$prefix='Administrator',$config=['ignore_request'=>true]){return parent::getModel($name,$prefix,$config);}
}
