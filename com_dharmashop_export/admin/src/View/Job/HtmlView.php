<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\View\Job;
use Dharma\Component\DharmaShopExport\Administrator\Export\{Access,Workflow};
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class HtmlView extends \Joomla\CMS\MVC\View\HtmlView {
 protected $option='com_dharmashop_export';protected array $job;
 public function display($tpl=null):void {
  $this->job=(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->job(Factory::getApplication()->getInput()->getInt('id'));Access::job($this->job);
  \Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_EXPORT_JOB').' #'.$this->job['id'],'download');
  $toolbar=\Joomla\CMS\Toolbar\Toolbar::getInstance();$toolbar->linkButton('profiles','COM_DHARMASHOP_EXPORT_PROFILES')->url('index.php?option='.$this->option.'&view=profiles')->icon('icon-list');
  if($this->getCurrentUser()->authorise('dharmashop.export.report',$this->option)){$toolbar->linkButton('history','COM_DHARMASHOP_EXPORT_HISTORY')->url('index.php?option='.$this->option.'&view=jobs')->icon('icon-history');}
  if($this->job['state']==='running' && $this->getCurrentUser()->authorise('dharmashop.export.run',$this->option)){$this->getDocument()->addHeadLink('index.php?option='.$this->option.'&view=job&id='.$this->job['id'],'canonical');$this->getDocument()->setMetaData('refresh','1;url=index.php?option='.$this->option.'&task=job.step&id='.$this->job['id'].'&'.Session::getFormToken().'=1','http-equiv');}
  parent::display($tpl);
 }
}
