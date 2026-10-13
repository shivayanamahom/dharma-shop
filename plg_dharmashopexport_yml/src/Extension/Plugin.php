<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopExport\YML\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Event\Event;
\defined('_JEXEC') or die;
final class Plugin extends CMSPlugin implements SubscriberInterface {
 protected $autoloadLanguage=true;
 public static function getSubscribedEvents():array {return ['onDharmaShopExportFormatsCollect'=>'collect'];}
 public function collect(Event $event):void {
  $formats=$event->getArgument('formats',[]);
  $formats['yml']=['label'=>'Яндекс (YML)','writer'=>new \Dharma\Plugin\DharmaShopExport\YML\Feed(), 'fieldset'=>'yml','tabular'=>false,'public'=>true,'form'=>JPATH_PLUGINS.'/dharmashopexport/yml/forms/yml.xml'];
  $event->setArgument('formats',$formats);
 }
}
