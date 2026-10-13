<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\View\Preview;
use Dharma\Component\DharmaShopExport\Administrator\Export\{Access,Workflow};
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_export';protected array $preview;
 public function display($tpl=null):void {Access::require('dharmashop.export.run');$this->preview=(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->preview(Factory::getApplication()->getInput()->getInt('id'));\Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT_PREVIEW'),'eye');\Joomla\CMS\Toolbar\Toolbar::getInstance()->linkButton('profiles','COM_DHARMASHOP_EXPORT_PROFILES')->url('index.php?option='.$this->option.'&view=profiles')->icon('icon-list');parent::display($tpl);}
}
