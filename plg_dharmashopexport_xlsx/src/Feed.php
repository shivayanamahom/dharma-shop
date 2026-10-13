<?php
/** Dharma Shop — потоковый XLSX, без сторонних библиотек. */
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopExport\Xlsx;
use Dharma\Component\DharmaShopExport\Administrator\Export\{FormatInterface,Cells};
\defined('_JEXEC') or die;
final class Feed implements FormatInterface {
 public function extension():string{return 'xlsx';}
 public function supports(string $entity):bool{return true;}
 public function required(array $settings):array{return [];}
 private function column(int $index):string{$s='';do{$s=chr(65+$index%26).$s;$index=intdiv($index,26)-1;}while($index>=0);return $s;}
 private function row($s,array $values,int $number,array $numeric=[]):void {
  if($number>1048576){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_XLSX_LIMIT');}
  Cells::put($s,'<row r="'.$number.'">');
  foreach($values as $i=>$v){$ref=$this->column($i).$number;
   if(!empty($numeric[$i])&&is_numeric($v)&&is_finite((float)$v)){Cells::put($s,'<c r="'.$ref.'" t="n"><v>'.Cells::xml((string)$v).'</v></c>');}
   else{$text=Cells::safe((string)$v);if(mb_strlen($text)>32767){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_XLSX_LIMIT');}Cells::put($s,'<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.Cells::xml($text).'</t></is></c>');}
  }Cells::put($s,'</row>');
 }
 public function head($s,array $c):void{Cells::put($s,'<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.(($c['settings']['xlsx_freeze']??1)?'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>':'').'<sheetData>');$this->row($s,array_column($c['columns'],'header'),1);}
 public function rows($s,array $rows,array $c):int {
  $number=(int)$c['processed']+2;
  foreach($rows as $row){$values=[];$numeric=[];foreach($c['columns'] as $col){$values[]=Cells::text($row[$col['key']]??null,$col['mode']??'',$c['settings']['list_separator']);$numeric[]=($c['schema'][$col['key']]['type']??'')==='number' && ($col['mode']??'')!=='yesno';}$this->row($s,$values,$number++,$numeric);}
  return count($rows);
 }
 /** Имя листа Excel: до 31 знака, без []:*?/\ и апострофа по краям; пустое — «Catalog». */
 private function sheetName(array $c):string {
  $name=trim(preg_replace('~[\[\]:*?/\\\\\x00-\x1F]~u','',(string)($c['settings']['xlsx_sheet']??'Catalog'))??'');$name=trim(mb_substr($name,0,31),"' ");
  return $name!==''?$name:'Catalog';
 }
 public function tail($s,array $c):void {
  Cells::put($s,'</sheetData>');
  if(!empty($c['settings']['xlsx_filter'])&&$c['columns']){Cells::put($s,'<autoFilter ref="A1:'.$this->column(count($c['columns'])-1).((int)($c['processed']??0)+1).'"/>');}
  Cells::put($s,'</worksheet>');
 }
 public function seal(string $source,string $destination,array $c):void {
  if(!class_exists(\ZipArchive::class)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_XLSX_ZIP');}
  $zip=new \ZipArchive();if($zip->open($destination,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
  $files=[
   '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
   '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
   'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.Cells::xml($this->sheetName($c)).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
   'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'
  ];
  foreach($files as $name=>$text){if(!$zip->addFromString($name,'<?xml version="1.0" encoding="UTF-8"?>'.$text)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}}
  if(!$zip->addFile($source,'xl/worksheets/sheet1.xml') || !$zip->close()){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
 }
}
