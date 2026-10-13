<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
/** Чтение источников каталога, ограниченное текущей порцией. Никакой записи в магазин. */
final class Catalog {
 public const ENTITIES=['products','categories','brands','badges','groups','fields','fieldgroups'];
 public function __construct(private DatabaseInterface $db) {}
 private array $schemas=[];
 private function rows(string $sql):array { return $this->db->setQuery($sql)->loadAssocList(); }
 public function schema(string $entity):array {
  if(isset($this->schemas[$entity])){return $this->schemas[$entity];}
  if(!in_array($entity,self::ENTITIES,true)) { throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID'); }
  $keys=['id'=>'number','title'=>'text','alias'=>'text','state'=>'number'];
  if(in_array($entity,['products','groups'],true)) {
   $keys+=['category_id'=>'number','category'=>'text','category_path'=>'text','category_alias'=>'text','category_ids'=>'items','brand_id'=>'number','brand'=>'text','brand_alias'=>'text','brand_path'=>'text','badge_ids'=>'items','badges'=>'items','badge_aliases'=>'items','introtext'=>'html','fulltext'=>'html','cover'=>'url','cover_alt'=>'text','gallery'=>'items','price'=>'number','in_stock'=>'number','public'=>'number','url'=>'url'];
  }
  if($entity==='products') { $keys+=['sku'=>'text','variant_id'=>'number','group_id'=>'number','group'=>'text','base_price'=>'number','discount'=>'text','discount_amount'=>'number','discount_start'=>'text','discount_end'=>'text','stock'=>'number','unit'=>'text','weight'=>'number','weight_unit'=>'text','length'=>'number','width'=>'number','height'=>'number','dimension_unit'=>'text','created'=>'text','modified'=>'text','metakey'=>'text','metadesc'=>'text','metadata'=>'text','params'=>'text','inherit_intro'=>'text','inherit_full'=>'text','inherit_images'=>'text']; }
  if($entity==='groups') { $keys+=['member_ids'=>'items','member_skus'=>'items','axes'=>'items','price_max'=>'number','display_mode'=>'text','title_template'=>'text','copy_fields'=>'number']; }
  if(in_array($entity,['categories','brands','badges'],true)) { $keys+=['parent_id'=>'number','path'=>'text','description'=>'html','introtext'=>'html','cover'=>'url','params'=>'text','metadesc'=>'text','metakey'=>'text','access'=>'number']; }
  if($entity==='fields') { $keys+=['target'=>'text','type'=>'text','store'=>'text','group_id'=>'number','group'=>'text','unit'=>'text','required'=>'number','all_categories'=>'number','category_ids'=>'items','options'=>'text','params'=>'text','level'=>'number','filterable'=>'number','sortable'=>'number','in_card'=>'number','in_page'=>'number','is_cover'=>'number','ordering'=>'number','access'=>'number']; }
  if($entity==='fieldgroups') { $keys+=['target'=>'text','tab_mode'=>'text','ordering'=>'number']; }
  $out=[];
  foreach($keys as $key=>$type) { $out[$key]=['label'=>Text::_('COM_DHARMASHOP_EXPORT_COL_'.strtoupper($key)),'type'=>$type]; }
  if(in_array($entity,['products','groups','categories','brands','badges'],true)) {
   $target=in_array($entity,['products','groups'],true)?'product':'category';
   foreach($this->rows('SELECT id,alias,title,store FROM #__dharmashop_fields WHERE state >= 0 AND target='.$this->db->quote($target).' ORDER BY group_id,ordering,id') as $f) {
    foreach([''=>'','@alias'=>' (alias)','@id'=>' (ID)'] as $suffix=>$label) { $out['field.'.$f['alias'].$suffix]=['label'=>$f['title'].$label,'type'=>'items','field_id'=>(int)$f['id'],'store'=>(string)$f['store']]; }
   }
  }
  return $this->schemas[$entity]=$out;
 }
 public function defaults(string $entity):array {
  $schema=$this->schema($entity);return array_values(array_filter(['id','title','alias','sku','category','brand','price','stock','group_id','cover'],fn($k)=>isset($schema[$k])));
 }
 private function source(string $entity):array {
  return match($entity) {
   'products'=>['#__dharmashop_products','state'], 'groups'=>['#__dharmashop_product_groups','state'],
   'fields'=>['#__dharmashop_fields','state'], 'fieldgroups'=>['#__dharmashop_fieldgroups','state'],
   'categories','brands','badges'=>['#__categories','published'],
   default=>throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID')
  };
 }
 private function where(array $p,int $after,int $ceiling):string {
  $e=$p['entity'];[, $state]=$this->source($e);$f=$p['params'];
  $where=['a.id > '.$after,'a.id <= '.$ceiling];
  $s=(string)($f['filter_state']??'1');$where[]=$s==='*'?'a.'.$state.' >= 0':'a.'.$state.' = '.(int)$s;
  if(in_array($e,['categories','brands','badges'],true)) {
   $extension=match($e){'brands'=>'com_dharmashop.brand','badges'=>'com_dharmashop.badge',default=>'com_dharmashop'};
   $where[]='a.extension = '.$this->db->quote($extension);$where[]='a.parent_id > 0';
  }
  $ids=array_values(array_filter(array_map('intval',(array)($f['categories']??[])),fn($v)=>$v>0));
  if($ids && in_array($e,['products','groups'],true)) {
   if($e==='products') { $where[]='EXISTS (SELECT 1 FROM #__dharmashop_product_categories pc WHERE pc.product_id=a.id AND pc.catid IN ('.implode(',',$ids).'))'; }
   else { $where[]='EXISTS (SELECT 1 FROM #__dharmashop_group_categories gc JOIN #__categories c ON c.id=gc.catid JOIN #__categories parent ON c.lft>=parent.lft AND c.rgt<=parent.rgt AND c.extension=parent.extension WHERE gc.group_id=a.id AND parent.id IN ('.implode(',',$ids).'))'; }
  }
  if(!empty($f['in_stock']) && in_array($e,['products','groups'],true)) { $where[]='a.in_stock=1'; }
  return implode(' AND ',$where);
 }
 public function ceiling(string $entity):int { [$table]=$this->source($entity);return (int)$this->db->setQuery('SELECT MAX(id) FROM '.$table)->loadResult(); }
 public function count(array $p,int $ceiling):int { [$table]=$this->source($p['entity']);return (int)$this->db->setQuery('SELECT COUNT(*) FROM '.$table.' a WHERE '.$this->where($p,0,$ceiling))->loadResult(); }
 public function page(array $p,int $after,int $ceiling,int $limit,array $keys):array {
  $e=$p['entity'];[$table,$state]=$this->source($e);
  $cols=match($e){
   'products'=>'a.id,a.title,a.alias,a.state,a.catid,a.group_id,a.introtext,a.`fulltext`,a.cover,a.cover_alt,a.price_min,a.in_stock,a.unit,a.created,a.modified,a.metakey,a.metadesc,a.metadata,a.params,a.inherit_intro,a.inherit_full,a.inherit_images',
   'groups'=>'a.id,a.title,a.alias,a.state,a.catid,a.brand_id,a.introtext,a.`fulltext`,a.cover,a.cover_alt,a.price_min,a.price_max,a.in_stock,a.display_mode,a.title_template,a.copy_fields',
   'fields'=>'a.id,a.title,a.alias,a.state,a.target,a.type,a.store,a.group_id,a.unit,a.required,a.all_categories,a.params,a.level,a.filterable,a.sortable,a.in_card,a.in_page,a.is_cover,a.ordering,a.access',
   'fieldgroups'=>'a.id,a.title,a.alias,a.state,a.target,a.tab_mode,a.ordering',
   default=>'a.id,a.title,a.alias,a.published AS state,a.parent_id,a.path,a.description,a.params,a.metadesc,a.metakey,a.access'
  };
  $rows=$this->rows('SELECT '.$cols.' FROM '.$table.' a WHERE '.$this->where($p,$after,$ceiling).' ORDER BY a.id LIMIT '.max(1,min(2000,$limit)));
  if(!$rows) { return []; }
  $by=[];foreach($rows as $r) { $by[(int)$r['id']]=$r; }$ids=implode(',',array_keys($by));
  $tax=[];$visible=[];$guest=(new \Joomla\CMS\User\User())->getAuthorisedViewLevels();
  if(in_array($e,['products','groups'],true)) {
   $relation=$e==='products'?'#__dharmashop_product_categories':'#__dharmashop_group_categories';$owner=$e==='products'?'product_id':'group_id';
   foreach($this->rows('SELECT pc.'.$owner.' AS owner,c.id,c.title,c.alias,c.path,c.extension,c.published,c.access,pc.is_main'.($e==='products'?',pc.direct':'').' FROM '.$relation.' pc JOIN #__categories c ON c.id=pc.catid WHERE pc.'.$owner.' IN ('.$ids.') ORDER BY c.lft,c.id') as $t) {
    $visible[$t['owner']]??=true;
    if((int)$t['published']!==1 || !in_array((int)$t['access'],$guest,true)){$visible[$t['owner']]=false;}
    // Витринный индекс содержит предков; экспортируем только прямые назначения.
    if($e==='products' && (int)$t['direct']!==1) { continue; }
    $tax[(int)$t['owner']][]=$t;
   }
  }
  $schema=$this->schema($e);$fieldIds=[];
  foreach($keys as $k) { if(isset($schema[$k]['field_id'])) { $fieldIds[$schema[$k]['field_id']]=true; } }
  if($e==='products') {
   foreach($this->rows('SELECT v.id AS variant_id,v.product_id,v.sku,v.stock_total AS stock,v.weight,v.weight_unit,v.length,v.width,v.height,v.dimension_unit,vp.price AS base_price,vp.price_final AS price,vp.discount,vp.discount_percent,vp.discount_start,vp.discount_end FROM #__dharmashop_variants v LEFT JOIN #__dharmashop_variant_prices vp ON vp.variant_id=v.id AND vp.price_list_id=(SELECT id FROM #__dharmashop_price_lists WHERE is_default=1 ORDER BY id LIMIT 1) WHERE v.product_id IN ('.$ids.')') as $v) {
    $by[(int)$v['product_id']]=array_merge($by[(int)$v['product_id']],$v);
   }
   $gids=array_filter(array_unique(array_map(fn($r)=>(int)($r['group_id']??0),$by)));
   $groups=[];if($gids) { foreach($this->rows('SELECT id,title,introtext,`fulltext` FROM #__dharmashop_product_groups WHERE id IN ('.implode(',',$gids).')') as $g) {$groups[(int)$g['id']]=$g;} }
   foreach($by as &$r) { $g=$groups[(int)($r['group_id']??0)]??[];$r['group']=$g['title']??'';
    // Скидка в записи формы товара: «500» (сумма) или «10%»; размер — разница базовой и итоговой цены на момент выгрузки.
    $r['discount']=(float)($r['discount']??0)>0?rtrim(rtrim(number_format((float)$r['discount'],2,'.',''),'0'),'.').((int)($r['discount_percent']??0)===1?'%':''):'';
    $r['discount_amount']=isset($r['base_price'],$r['price'])?number_format(max(0.0,(float)$r['base_price']-(float)$r['price']),2,'.',''):'';
    foreach(['introtext'=>'inherit_intro','fulltext'=>'inherit_full'] as $col=>$flag) { if($r[$flag]==='group') {$r[$col]=$g[$col]??'';}elseif($r[$flag]==='empty'){$r[$col]='';} }
   }unset($r);
  }
  if($e==='groups') {
   foreach($this->rows('SELECT p.id,p.group_id,v.sku FROM #__dharmashop_products p LEFT JOIN #__dharmashop_variants v ON v.product_id=p.id WHERE p.group_id IN ('.$ids.') AND p.state>=0 ORDER BY p.id') as $m) { $by[$m['group_id']]['member_ids'][]=$m['id'];$by[$m['group_id']]['member_skus'][]=$m['sku']; }
   foreach($this->rows('SELECT a.group_id,f.alias FROM #__dharmashop_group_axes a JOIN #__dharmashop_fields f ON f.id=a.field_id WHERE a.group_id IN ('.$ids.') ORDER BY a.ordering,f.id') as $a) {$by[$a['group_id']]['axes'][]=$a['alias'];}
  }
  if($e==='fields') {
   $options=[];foreach($this->rows('SELECT field_id,id,alias,title,color,image,ordering FROM #__dharmashop_field_options WHERE field_id IN ('.$ids.') ORDER BY ordering,id') as $o) { $options[$o['field_id']][]=$o; }
   foreach($options as $id=>$optionsForField) {$by[$id]['options']=json_encode($optionsForField,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
   foreach($this->rows('SELECT field_id,catid FROM #__dharmashop_category_fields WHERE field_id IN ('.$ids.') ORDER BY catid') as $c) {$by[$c['field_id']]['category_ids'][]=$c['catid'];}
   $gids=array_filter(array_unique(array_column($by,'group_id')));$groupNames=[];
   if($gids) { foreach($this->rows('SELECT id,title FROM #__dharmashop_fieldgroups WHERE id IN ('.implode(',',array_map('intval',$gids)).')') as $g) {$groupNames[$g['id']]=$g['title'];} }
   foreach($by as &$r) {$r['group']=$groupNames[$r['group_id']]??'';}unset($r);
  }
  if($fieldIds) { $this->fields($by,$e,array_keys($fieldIds),$p); }
  $gallery=[];
  if($e==='products' && in_array('gallery',$keys,true)) {
   foreach($this->rows('SELECT product_id AS owner,path FROM #__dharmashop_field_media WHERE product_id IN ('.$ids.') AND field_id=0 AND variant_id IS NULL ORDER BY ordering,id') as $m) {$gallery[$m['owner']][]=$this->absolute($m['path'],$p);}
  }
  $gids=$e==='groups'?array_keys($by):array_filter(array_unique(array_map(fn($r)=>(int)($r['group_id']??0),$by)));$groupGallery=[];
  if($gids && in_array('gallery',$keys,true)) { foreach($this->rows('SELECT group_id,path FROM #__dharmashop_group_media WHERE group_id IN ('.implode(',',$gids).') ORDER BY ordering,id') as $m) {$groupGallery[$m['group_id']][]=$this->absolute($m['path'],$p);} }
  foreach($by as $id=>&$r) {
   if(in_array($e,['products','groups'],true)) {
    $r['public']=(int)$r['state']===1 && ($visible[$id]??false) ? 1 : 0;
    $r['category_id']=$r['catid'];$r['price']=$r['price']??$r['price_min'];
    foreach($tax[$id]??[] as $t) {
     if($t['extension']==='com_dharmashop') { $r['category_ids'][]=$t['id'];if((int)$r['catid']===(int)$t['id']) {$r['category']=$t['title'];$r['category_alias']=$t['alias'];$r['category_path']=$t['path'];} }
     if($t['extension']==='com_dharmashop.brand') {$r['brand_id']=$t['id'];$r['brand']=$t['title'];$r['brand_alias']=$t['alias'];$r['brand_path']=$t['path'];}
     if($t['extension']==='com_dharmashop.badge') {$r['badge_ids'][]=$t['id'];$r['badges'][]=$t['title'];$r['badge_aliases'][]=$t['alias'];}
    }
    $r['cover']=$this->absolute($r['cover']??'',$p);
    $r['gallery']=$e==='groups'?($groupGallery[$id]??[]):match($r['inherit_images']){'group'=>$groupGallery[$r['group_id']]??[],'empty'=>[],default=>$gallery[$id]??[]};
    $r['url']=$this->productUrl($e==='products'?$id:(int)($r['member_ids'][0]??0),(int)$r['catid'],$p);
   }elseif(in_array($e,['categories','brands','badges'],true)) { $params=json_decode((string)$r['params'],true)?:[];$r['introtext']=$params['introtext']??'';$r['cover']=$this->absolute($params['image']??'',$p); }
  }unset($r);
  return array_values($by);
 }
 private function fields(array &$by,string $e,array $fids,array $p):void {
  $owner=match($e){'products'=>'product_id','groups'=>'group_id',default=>'category_id'};
  $prefix=match($e){'products'=>'#__dharmashop_','groups'=>'#__dharmashop_group_',default=>'#__dharmashop_category_'};
  $where='v.'.$owner.' IN ('.implode(',',array_keys($by)).') AND v.field_id IN ('.implode(',',$fids).')'.($e==='products'?' AND v.variant_id IS NULL':'');
  foreach($this->rows('SELECT v.'.$owner.' AS owner,f.alias,v.option_id,v.num,v.num_end,v.str,o.title,o.alias AS option_alias FROM '.$prefix.'field_values v JOIN #__dharmashop_fields f ON f.id=v.field_id LEFT JOIN #__dharmashop_field_options o ON o.id=v.option_id AND o.field_id=v.field_id WHERE '.$where.' ORDER BY o.ordering,v.id') as $v) {
   $value=$v['option_id']!==null?($v['title']??''):($v['num']!==null?$this->decimal($v['num']).($v['num_end']!==null?'–'.$this->decimal($v['num_end']):''):($v['str']??''));
   $key='field.'.$v['alias'];$by[$v['owner']][$key][]=$value;$by[$v['owner']][$key.'@alias'][]=$v['option_alias']??$value;$by[$v['owner']][$key.'@id'][]=$v['option_id']??$value;
  }
  foreach($this->rows('SELECT v.'.$owner.' AS owner,f.alias,v.value FROM '.$prefix.'field_texts v JOIN #__dharmashop_fields f ON f.id=v.field_id WHERE '.$where.' ORDER BY v.id') as $v) {foreach(['','@alias','@id'] as $s){$by[$v['owner']]['field.'.$v['alias'].$s]=$v['value'];}}
  foreach($this->rows('SELECT v.'.$owner.' AS owner,f.alias,v.path FROM '.$prefix.'field_media v JOIN #__dharmashop_fields f ON f.id=v.field_id WHERE '.$where.' ORDER BY v.ordering,v.id') as $v) {foreach(['','@alias','@id'] as $s){$by[$v['owner']]['field.'.$v['alias'].$s][]=$this->absolute($v['path'],$p);}}
 }
 private function decimal(string $v):string {return str_contains($v,'.')?rtrim(rtrim($v,'0'),'.'):$v;}
 /** Адрес карточки на витрине: ЧПУ через маршрутизатор сайта (если он доступен вне консоли), иначе обычная ссылка компонента. Начинается с site_url профиля. */
 private function productUrl(int $id,int $catid,array $p):string {
  $base=$this->baseUrl($p);$query='index.php?option=com_dharmashop&view=product&id='.$id.($catid>0?'&catid='.$catid:'');
  if($id>0 && !empty($p['params']['sef_urls']) && !\Joomla\CMS\Factory::getApplication()->isClient('cli')) {
   try {
    $link=(string)\Joomla\CMS\Router\Route::link('site',$query,false,\Joomla\CMS\Router\Route::TLS_IGNORE,false);
    $path=(string)parse_url($link,PHP_URL_PATH);$root=\Joomla\CMS\Uri\Uri::root(true);
    if($root!=='' && str_starts_with($path,$root.'/')) { $path=substr($path,strlen($root)); }
    if($path!=='' && $path[0]==='/' && !str_contains($path,'option=')) { return $base.$path; }
   } catch(\Throwable) {}
  }
  return $base.'/'.$query;
 }
 public function baseUrl(array $p):string {
  $url=rtrim((string)($p['params']['site_url']??''),'/');$parts=parse_url($url);
  if(!$parts || !in_array(strtolower($parts['scheme']??''),['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_SITE_REQUIRED');}
  return $url;
 }
 public function absolute(string $path,array $p):string {
  $path=explode('#',$path,2)[0];if($path==='') {return '';}
  if(preg_match('~^https?://~i',$path)) {return $path;}
  if(str_starts_with($path,'//')) {return 'https:'.$path;}
  if(str_contains($path,':') || preg_match('~(^|/)\.\.(/|$)~',$path)) {return '';}
  return $this->baseUrl($p).'/'.implode('/',array_map(static fn($segment)=>rawurlencode(rawurldecode($segment)),explode('/',ltrim($path,'/'))));
 }
 public function context(array $p,array $columns):array {
  $c=['settings'=>$p['params'],'columns'=>$columns,'schema'=>$this->schema($p['entity']),'site_url'=>$this->baseUrl($p),'entity'=>$p['entity'],'currency'=>ComponentHelper::getParams('com_dharmashop')->get('currency_code','RUB'),'shop_name'=>ComponentHelper::getParams('com_dharmashop')->get('shop_name','')];
  if($p['format']==='yml') {$c['categories']=$this->rows("SELECT id,parent_id,title FROM #__categories WHERE extension='com_dharmashop' AND published=1 AND parent_id>0 ORDER BY lft");}
  return $c;
 }
}
