<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
\defined('_JEXEC') or die;
final class Formats {
 private array $items;
 public function __construct() {
  $this->items=['csv'=>['label'=>'CSV','writer'=>new Csv(),'form'=>null,'fieldset'=>'csv','tabular'=>true]];
  PluginHelper::importPlugin('dharmashopexport');
  $event=new Event('onDharmaShopExportFormatsCollect',['formats'=>$this->items]);
  Factory::getApplication()->getDispatcher()->dispatch($event->getName(),$event);
  $items=(array)$event->getArgument('formats',[]);
  foreach($items as $key=>$item) {
   if(!preg_match('/^[a-z][a-z0-9_]{0,31}$/D',(string)$key) || !($item['writer']??null) instanceof FormatInterface || !preg_match('/^[a-z0-9]{1,8}$/D',$item['writer']->extension())) { throw new \UnexpectedValueException('COM_DHARMASHOP_EXPORT_INVALID_FORMAT'); }
  }
  $this->items=$items;
 }
 public function all():array { return $this->items; }
 public function get(string $id):FormatInterface { return $this->items[$id]['writer'] ?? throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FORMAT'); }
}
