<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopExport\Xlsx\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Event\Event;
\defined('_JEXEC') or die;
final class Plugin extends CMSPlugin implements SubscriberInterface {
 protected $autoloadLanguage=true;
 public static function getSubscribedEvents():array {return ['onDharmaShopExportFormatsCollect'=>'collect'];}
 public function collect(Event $event):void {
  $formats=$event->getArgument('formats',[]);
  $formats['xlsx']=['label'=>'Excel (XLSX)','writer'=>new \Dharma\Plugin\DharmaShopExport\Xlsx\Feed(), 'fieldset'=>'xlsx','tabular'=>true,'form'=>JPATH_PLUGINS.'/dharmashopexport/xlsx/forms/xlsx.xml'];
  $event->setArgument('formats',$formats);
 }
}
