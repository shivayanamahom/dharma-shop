<?php
/** Isolated synthetic value storage; real EntityFields and CategoryModel queries, no changes to shop tables. */
declare(strict_types=1);
require __DIR__.'/../boot.php';
use Joomla\CMS\Factory;
use Joomla\Database\{DatabaseInterface,DatabaseFactory};
use Dharma\Component\DharmaShop\Site\Support\EntityFields;
$db=Factory::getContainer()->get(DatabaseInterface::class);$app=Factory::getApplication();$component=$app->bootComponent('com_dharmashop');
$app->loadIdentity(new \Joomla\CMS\User\User());$config=Factory::getConfig();
$prefix='cgqa_'.bin2hex(random_bytes(4)).'_';
$bench=(new DatabaseFactory())->getDriver('mysqli',['host'=>$config->get('host'),'user'=>$config->get('user'),'password'=>$config->get('password'),'database'=>$config->get('db'),'prefix'=>$prefix,'select'=>true]);
$demo=json_decode(file_get_contents(__DIR__.'/../../dist/common-fields-demo.json'),true,512,JSON_THROW_ON_ERROR);
$templateId=(int)$demo['product_ids'][0];
$catid=(int)$db->setQuery('SELECT catid FROM #__dharmashop_products WHERE id='.$templateId)->loadResult();
if(!$catid)throw new RuntimeException('Generate a --demo fixture first.');
$tables=[];$report=[];
$run=static function(string $sql)use($bench){$bench->setQuery($sql)->execute();};
try{
 foreach(['categories','dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_fields','dharmashop_fieldgroups','dharmashop_field_options','dharmashop_category_fields','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media','dharmashop_category_field_values','dharmashop_category_field_texts','dharmashop_category_field_media','dharmashop_product_groups','dharmashop_group_categories','dharmashop_group_axes','dharmashop_group_media'] as $suffix){
  $table=$prefix.$suffix;$tables[]=$table;$run('CREATE TABLE '.$bench->quoteName($table).' LIKE '.$db->quoteName($db->getPrefix().$suffix));
  if(!in_array($suffix,['dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media'],true))$run('INSERT INTO '.$bench->quoteName($table).' SELECT * FROM '.$db->quoteName($db->getPrefix().$suffix));
 }
 $seq=$prefix.'seq';$tables[]=$seq;$run('CREATE TABLE '.$seq.' (n int unsigned PRIMARY KEY) ENGINE=InnoDB');
 // Cross join digits avoids server-specific recursion limits and does not touch shared benchmark tables.
 $digits='(SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
 $run('INSERT INTO '.$seq.' SELECT 1+a.d+10*b.d+100*c.d+1000*d.d+10000*e.d FROM '.$digits.' a CROSS JOIN '.$digits.' b CROSS JOIN '.$digits.' c CROSS JOIN '.$digits.' d CROSS JOIN '.$digits.' e');
 foreach([100,100000] as $n){
  foreach(['dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media'] as $suffix)$run('TRUNCATE TABLE #__'.$suffix);
  // Repeat the fully populated product's typed values across synthetic IDs.
  foreach(['dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media'] as $suffix){
   $cols=array_keys($db->getTableColumns('#__'.$suffix));$select=[];$insert=[];
   foreach($cols as $col){if($col==='id' && !in_array($suffix,['dharmashop_products','dharmashop_variants'],true))continue;$insert[]=$bench->quoteName($col);
    $select[]=match(true){$col==='id' && $suffix==='dharmashop_products'=>'s.n',$col==='id' && $suffix==='dharmashop_variants'=>'s.n',$col==='product_id'=>'s.n',$col==='variant_id' && $suffix==='dharmashop_variant_prices'=>'s.n',$col==='alias' && $suffix==='dharmashop_products'=>"CONCAT('synthetic-',s.n)",$col==='sku'=>"CONCAT('QA-',s.n)",$col==='group_id' && $suffix==='dharmashop_products'=>'NULL',default=>'t.'.$bench->quoteName($col)};
   }
   $condition=match($suffix){'dharmashop_products'=>'t.id='.$templateId,'dharmashop_variants','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media'=>'t.product_id='.$templateId,'dharmashop_variant_prices'=>'t.variant_id=(SELECT id FROM '.$db->getPrefix().'dharmashop_variants WHERE product_id='.$templateId.')'};
   $run('INSERT INTO #__'.$suffix.' ('.implode(',',$insert).') SELECT '.implode(',',$select).' FROM '.$db->getPrefix().$suffix.' t CROSS JOIN '.$seq.' s WHERE s.n<='.$n.' AND '.$condition);
  }
  $monitor=new \Joomla\Database\Monitor\DebugMonitor();$bench->setMonitor($monitor);
  $probe=array_map(static fn($id)=>(object)['id'=>$id],range($n-23,$n));EntityFields::attach($bench,$probe,'card');$bench->setMonitor(null);
  $plans=[];
  foreach($monitor->getLogs() as $sql){
   if(str_contains($sql,'group_field_'))throw new RuntimeException('Frontend reads group defaults');
   foreach($bench->setQuery('EXPLAIN '.$sql)->loadAssocList() as $row){
    if($row['table']==='v'){
     if($row['key']!=='idx_product' || !in_array($row['type'],['range','ref'],true))throw new RuntimeException('Unbounded value read');
     $plans[]=['index'=>$row['key'],'access'=>$row['type'],'estimated_rows'=>(int)$row['rows']];
    }
   }
  }
  $times=[];$rows=0;
  for($i=0;$i<21;$i++){$items=array_map(static fn($id)=>(object)['id'=>$id],range($n-23,$n));$start=hrtime(true);EntityFields::attach($bench,$items,'card');$ms=(hrtime(true)-$start)/1e6;if($i)$times[]=$ms;$rows=count($items[0]->fields);}
  sort($times);$report[$n]=['card24_p50_ms'=>round(($times[9]+$times[10])/2,2),'card24_p95_ms'=>round($times[18],2),'displayed_fields_per_product'=>$rows,'queries'=>count($monitor->getLogs()),'plans'=>$plans,'value_rows'=>(int)$bench->setQuery('SELECT COUNT(*) FROM #__dharmashop_field_values')->loadResult()];
  $model=$component->getMVCFactory()->createModel('Category','Site',['ignore_request'=>true]);$model->setDatabase($bench);$model->forCategory($catid);$listing=[];
  for($i=0;$i<21;$i++){$t=hrtime(true);$items=$model->getProducts('new',24);$model->attachCardData($items,1);$ms=(hrtime(true)-$t)/1e6;if($i)$listing[]=$ms;}
  if(count($items)!==24 || $rows<1)throw new RuntimeException('Incomplete cards');
  sort($listing);$report[$n]['listing24_p50_ms']=round(($listing[9]+$listing[10])/2,2);$report[$n]['listing24_p95_ms']=round($listing[18],2);$report[$n]['listing_count']=count($items);
 }
 file_put_contents(__DIR__.'/../../dist/common-fields-scale.json',json_encode($report,JSON_PRETTY_PRINT));echo json_encode($report,JSON_PRETTY_PRINT)."\n";
}finally{foreach(array_reverse($tables) as $table)$run('DROP TABLE IF EXISTS '.$bench->quoteName($table));echo "Only own isolated benchmark tables removed.\n";}
