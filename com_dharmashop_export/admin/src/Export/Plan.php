<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
use Joomla\CMS\Component\ComponentHelper;
\defined('_JEXEC') or die;
final class Plan {
 public static function prepare(array $profile,Catalog $catalog):array {
  $entity=(string)($profile['entity']??'products');$format=(string)($profile['format']??'csv');
  $writer=(new Formats())->get($format);
  if(!$writer->supports($entity)) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_FORMAT');}
  $p=(array)($profile['params']??[]);$schema=$catalog->schema($entity);$columns=[];
  foreach((array)(((new Formats())->all()[$format]['tabular']??true)?($p['table_columns']??$p['columns']??[]):[]) as $col) {
   $col=(array)$col;$key=(string)($col['key']??'');$mode=(string)($col['mode']??'');
   if($key===''){continue;} // строка без выбранного поля (очищенный список) пропускается
   if(!isset($schema[$key]) || !in_array($mode,['','plain','yesno','emptyzero'],true)) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_COLUMNS');}
   $header=trim((string)($col['header']??''));$columns[]=['key'=>$key,'mode'=>$mode,'header'=>$header!==''?mb_substr($header,0,255):$schema[$key]['label']];
  }
  if(!$columns) {foreach($catalog->defaults($entity) as $key){$columns[]=['key'=>$key,'mode'=>'','header'=>$schema[$key]['label']];}}
  if(count($columns)>200) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_COLUMNS');}
  $p['table_columns']=$p['table_columns']??$p['columns']??$columns;
  if((new Formats())->all()[$format]['tabular']??true){$p['table_columns']=$columns;}
  if(!((new Formats())->all()[$format]['tabular']??true)){$columns=[];foreach($writer->required($p) as $key){if(isset($schema[$key])){$columns[]=['key'=>$key,'mode'=>'','header'=>$schema[$key]['label']];}}}
  $p['columns']=$columns;
  $p['filter_state']=in_array((string)($p['filter_state']??'1'),['1','0','*'],true)?(string)($p['filter_state']??'1'):'1';
  foreach(['categories','brands','badges','category_branch','brand_branch'] as $filter){$p[$filter]=array_values(array_unique(array_filter(array_map('intval',(array)($p[$filter]??[])),fn($id)=>$id>0)));}
  $p['in_stock']=empty($p['in_stock'])?0:1;
  $p['sef_urls']=(int)($p['sef_urls']??1)===0?0:1;
  // Формат-фид (YML) предназначен для скачивания площадкой по ссылке, поэтому всегда публичный; для остальных — выбор профиля.
  $p['visibility']=(!empty((new Formats())->all()[$format]['public'])||($p['visibility']??'private')==='public')?'public':'private';
  $p['file_name']=trim((string)($p['file_name']??''))?:'catalog';
  if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D',$p['file_name'])) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_FILENAME');}
  $p['list_separator']=mb_substr((string)($p['list_separator']??' | '),0,20);
  if($p['list_separator']===''){$p['list_separator']=' | ';}
  $p['csv_separator']=in_array($p['csv_separator']??';',[';',',','tab'],true)?($p['csv_separator']??';'):';';
  $p['csv_bom']=isset($p['csv_bom'])?(int)(bool)$p['csv_bom']:1;
  $p['site_url']=rtrim(trim((string)($p['site_url']??'')),'/');
  $profile=['id'=>(int)($profile['id']??0),'title'=>mb_substr((string)($profile['title']??''),0,255),'state'=>(int)($profile['state']??1),'entity'=>$entity,'format'=>$format,'params'=>$p];
  $catalog->baseUrl($profile);
  foreach(array_unique(array_merge(array_column($columns,'key'),$writer->required($p))) as $key) {if(!isset($schema[$key])){throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_COLUMNS');}}
  return $profile;
 }
 public static function storage():array {
  $p=ComponentHelper::getParams('com_dharmashop');
  return ['folder'=>(string)$p->get('export_folder','files/export'),'keep'=>max(1,min(200,(int)$p->get('export_keep',10))),
   'batch'=>max(10,min(2000,(int)$p->get('export_batch',200))),'budget'=>max(1,min(30,(int)$p->get('export_budget',5)))];
 }
}
