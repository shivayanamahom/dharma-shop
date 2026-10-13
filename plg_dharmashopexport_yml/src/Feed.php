<?php
/** Dharma Shop — YML с собственным сопоставлением и проверкой предложений. */
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopExport\YML;
use Dharma\Component\DharmaShopExport\Administrator\Export\{FormatInterface,Cells};
use Joomla\CMS\Component\ComponentHelper;
\defined('_JEXEC') or die;
final class Feed implements FormatInterface {
 private const MAP=['name'=>'title','price'=>'price','oldprice'=>'base_price','vendor'=>'brand','vendorCode'=>'sku','url'=>'url','picture'=>'gallery','description'=>'introtext','available'=>'in_stock'];
 public function extension():string{return 'yml';}
 public function supports(string $entity):bool{return $entity==='products';}
 private function map(array $s):array{$map=[];foreach(self::MAP as $key=>$default){$map[$key]=(string)($s['yml_'.$key]??$default);}return $map;}
 public function required(array $settings):array {
  $keys=['id','category_id','cover','public'];if(!empty($settings['yml_groups'])){$keys[]='group_id';}
  $keys=array_merge($keys,array_values($this->map($settings)));
  foreach((array)($settings['yml_params']??[]) as $param){$keys[]=(string)($param['source']??'');}
  return array_values(array_unique(array_filter($keys)));
 }
 private function tag($s,string $key,string $value):void{if($value!==''){Cells::put($s,'<'.$key.'>'.Cells::xml($value).'</'.$key.'>');}}
 private function currency(array $c):string { $code=strtoupper((string)($c['currency']??'RUB'));return $code==='RUB'?'RUR':$code; }
 public function head($s,array $c):void {
  $settings=$c['settings'];$name=trim((string)($settings['yml_shop']??''))?:($c['shop_name']?:'Dharma Shop');$company=trim((string)($settings['yml_company']??''))?:$name;
  Cells::put($s,'<?xml version="1.0" encoding="UTF-8"?><yml_catalog date="'.gmdate('Y-m-d\TH:i:s\Z',strtotime($c['started'].' UTC')).'"><shop>');
  $this->tag($s,'name',$name);$this->tag($s,'company',$company);$this->tag($s,'url',$c['site_url']);
  Cells::put($s,'<currencies><currency id="'.Cells::xml($this->currency($c)).'" rate="1"/></currencies><categories>');
  $ids=array_fill_keys(array_column($c['categories'],'id'),true);
  foreach($c['categories'] as $cat){Cells::put($s,'<category id="'.(int)$cat['id'].'"'.(isset($ids[$cat['parent_id']])?' parentId="'.(int)$cat['parent_id'].'"':'').'>'.Cells::xml($cat['title']).'</category>');}
  Cells::put($s,'</categories><offers>');
 }
 public function rows($s,array $rows,array $c):int {
  $map=$this->map($c['settings']);$written=0;$categories=array_fill_keys(array_column($c['categories'],'id'),true);
  foreach($rows as $row){$v=[];foreach($map as $tag=>$key){$v[$tag]=$key!==''?($row[$key]??''):'';}
   $price=is_numeric($v['price'])?(float)$v['price']:0;$description=trim(Cells::text($v['description'],'plain'));
   $picture=array_values(array_unique(array_filter((array)$v['picture'])));if(!$picture && !empty($row['cover'])){$picture=[$row['cover']];}
   $error=empty($row['public'])?'COM_DHARMASHOP_EXPORT_YML_NOT_PUBLIC':(trim(Cells::text($v['name']))===''?'COM_DHARMASHOP_EXPORT_YML_NO_NAME':($price<=0?'COM_DHARMASHOP_EXPORT_YML_NO_PRICE':(!isset($categories[$row['category_id']])?'COM_DHARMASHOP_EXPORT_YML_NO_CATEGORY':(!preg_match('~^https?://~i',Cells::text($v['url']))?'COM_DHARMASHOP_EXPORT_YML_NO_URL':(!$picture?'COM_DHARMASHOP_EXPORT_YML_NO_PICTURE':($description===''?'COM_DHARMASHOP_EXPORT_YML_NO_DESCRIPTION':''))))));
   if($error==='' && !array_filter($picture,static fn($url)=>preg_match('~^https?://~i',(string)$url))){$error='COM_DHARMASHOP_EXPORT_YML_NO_PICTURE';}
   if($error!==''){($c['issue'])($error,(int)$row['id']);if(!empty($c['settings']['yml_strict'])){throw new \RuntimeException($error);}continue;}
   Cells::put($s,'<offer id="'.(int)$row['id'].'" available="'.(!empty($v['available'])?'true':'false').'"'.(!empty($c['settings']['yml_groups'])&&!empty($row['group_id'])?' group_id="'.(int)$row['group_id'].'"':'').'>');
   $this->tag($s,'url',Cells::text($v['url']));$this->tag($s,'price',number_format($price,2,'.',''));
   $oldRaw=trim((string)$v['oldprice']);$old=is_numeric($oldRaw)?(float)$oldRaw:0;
   if($oldRaw!=='' && $old>$price){$discount=(($old-$price)/$old)*100;
    if(floor($old)===$old && $discount>=5 && $discount<=75){$this->tag($s,'oldprice',number_format($old,0,'.',''));}
    else{($c['issue'])('COM_DHARMASHOP_EXPORT_YML_OLDPRICE_INVALID',(int)$row['id']);}
   }elseif($oldRaw!=='' && !is_numeric($oldRaw)){($c['issue'])('COM_DHARMASHOP_EXPORT_YML_OLDPRICE_INVALID',(int)$row['id']);}
   $this->tag($s,'currencyId',$this->currency($c));$this->tag($s,'categoryId',(string)$row['category_id']);
   foreach(array_slice($picture,0,10) as $url){if(preg_match('~^https?://~i',(string)$url)){$this->tag($s,'picture',(string)$url);}}
   foreach(['name','vendor','vendorCode'] as $tag){$this->tag($s,$tag,Cells::text($v[$tag]));}
   $this->tag($s,'description',mb_substr($description,0,3000));
   foreach((array)($c['settings']['yml_params']??[]) as $param){$source=(string)($param['source']??'');$name=trim((string)($param['name']??''));$unit=trim((string)($param['unit']??''));if($name===''){$name=$c['schema'][$source]['label']??$source;}
    foreach((array)($row[$source]??[]) as $value){if((string)$value!==''){Cells::put($s,'<param name="'.Cells::xml($name).'"'.($unit!==''?' unit="'.Cells::xml($unit).'"':'').'>'.Cells::xml(Cells::text($value,'plain')).'</param>');}}
   }
   Cells::put($s,'</offer>');$written++;
  }return $written;
 }
 public function tail($s,array $c):void{Cells::put($s,'</offers></shop></yml_catalog>');}
 public function seal(string $source,string $destination,array $c):void{if(!rename($source,$destination)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}}
}
