<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
\defined('_JEXEC') or die;
final class Csv implements FormatInterface {
 public function extension():string { return 'csv'; }
 public function supports(string $entity):bool { return true; }
 public function required(array $settings):array { return []; }
 private function line($stream,array $values,array $c):void {
  $d=$c['settings']['csv_separator'] ?? ';';$d= $d === 'tab' ? "\t" : $d;
  if(!in_array($d,[';',',',"\t"],true)) { throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID'); }
  if(fputcsv($stream,$values,$d,'"','',"\r\n")===false) { throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR'); }
 }
 public function head($stream,array $c):void {
  if(!empty($c['settings']['csv_bom'])) { Cells::put($stream,"\xEF\xBB\xBF"); }
  $this->line($stream,array_map(static fn($v)=>Cells::safe($v['header']),$c['columns']),$c);
 }
 public function rows($stream,array $rows,array $c):int {
  foreach($rows as $row) {
   $values=[];
   foreach($c['columns'] as $col) { $values[]=Cells::safe(Cells::text($row[$col['key']]??null,$col['mode']??'', $c['settings']['list_separator']??' | ')); }
   $this->line($stream,$values,$c);
  }
  return count($rows);
 }
 public function tail($stream,array $c):void {}
 public function seal(string $source,string $destination,array $c):void { if(!rename($source,$destination)) { throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR'); } }
}
