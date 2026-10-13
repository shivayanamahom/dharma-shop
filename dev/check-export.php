<?php
/** Export integration and scale checks on isolated tables only. Catalog records are read-only. */
declare(strict_types=1);
require __DIR__.'/boot.php';
use Joomla\CMS\Factory;
use Joomla\Database\{DatabaseFactory,DatabaseInterface};
use Dharma\Component\DharmaShopExport\Administrator\Export\{Catalog,Plan,Workflow,Files,Csv,Cells};
$app=Factory::getApplication();$app->bootComponent('com_dharmashop_export');$app->getLanguage()->load('com_dharmashop_export',JPATH_ADMINISTRATOR,'ru-RU',true);
$original=Factory::getContainer()->get(DatabaseInterface::class);$config=Factory::getConfig();$tag='exqa_'.bin2hex(random_bytes(4));$prefix=$tag.'_';
$db=(new DatabaseFactory())->getDriver('mysqli',['host'=>$config->get('host'),'user'=>$config->get('user'),'password'=>$config->get('password'),'database'=>$config->get('db'),'prefix'=>$prefix,'select'=>true]);
$tables=[];$folders=[];$checks=0;$report=[];
$run=static fn(string $sql)=>$db->setQuery($sql)->execute();
$assert=static function(bool $ok,string $message)use(&$checks){if(!$ok){throw new RuntimeException($message);}++$checks;echo "PASS $message\n";};
$make=static function(string $entity,string $format='csv',array $params=[])use($db){
 $row=(object)['title'=>'QA '.$entity.' '.$format,'state'=>1,'entity'=>$entity,'format'=>$format,'params'=>json_encode($params+['site_url'=>'https://test.test','filter_state'=>'*','csv_bom'=>1,'file_name'=>'qa','visibility'=>'private'])];$db->insertObject('#__dharmashop_export_profiles',$row,'id');return (int)$row->id;
};
$start=static function(Workflow $w,int $profile,array $extra=[])use($db,$tag,&$folders){
 $id=$w->start($profile);$j=$w->job($id);$s=$j['snapshot'];$s['storage']['folder']='files/'.$tag;$s['storage']['keep']=2;$s['storage']['batch']=200;$s['storage']['budget']=1;
 $s['storage']=array_merge($s['storage'],$extra);$folders[$s['storage']['folder']]=true;
 $db->setQuery('UPDATE #__dharmashop_export_jobs SET snapshot='.$db->quote(json_encode($s)).' WHERE id='.$id)->execute();return $id;
};
$finish=static function(Workflow $w,int $id) {for($i=0;$i<2000;$i++){$j=$w->step($id);if($j['state']!=='running'){return $j;}}throw new RuntimeException('Too many steps');};
$read=static function(array $j):string{$s=(new Files($j['snapshot']['storage']))->stream((int)$j['id'],'artifact.php');$content=stream_get_contents($s);fclose($s);return $content;};
try{
 foreach(['categories','dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_price_lists','dharmashop_product_categories','dharmashop_fields','dharmashop_fieldgroups','dharmashop_field_options','dharmashop_category_fields','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media','dharmashop_category_field_values','dharmashop_category_field_texts','dharmashop_category_field_media','dharmashop_product_groups','dharmashop_group_categories','dharmashop_group_axes','dharmashop_group_media','dharmashop_group_field_values','dharmashop_group_field_texts','dharmashop_group_field_media','dharmashop_export_profiles','dharmashop_export_jobs'] as $suffix){
  $table=$prefix.$suffix;$run('CREATE TABLE '.$db->quoteName($table).' LIKE '.$db->quoteName($original->getPrefix().$suffix));$tables[]=$table;
  if(!str_contains($suffix,'export_')){$run('INSERT INTO '.$db->quoteName($table).' SELECT * FROM '.$db->quoteName($original->getPrefix().$suffix));}
 }
 $w=new Workflow($db);$catalog=new Catalog($db);
 foreach(Catalog::ENTITIES as $e){
  $schema=$catalog->schema($e);$params=['columns'=>array_map(fn($key)=>['key'=>$key,'header'=>$schema[$key]['label'],'mode'=>''],array_keys($schema))];
  $profile=$make($e,'csv',$params);$id=$start($w,$profile);$job=$finish($w,$id);
  $assert($job['state']==='done',$e.' CSV completed '.($job['error']??''));
  $data=$read($job);$f=fopen('php://temp','w+b');fwrite($f,$data);rewind($f);$lines=0;while(fgetcsv($f,0,';','"','')!==false){$lines++;}fclose($f);
  $assert($lines===(int)$job['written']+1 && $job['processed']===$job['written'],$e.' exact rows with quoted multiline values');
  $assert(hash('sha256',$data)===$job['sha256'] && strlen($data)===(int)$job['bytes'],$e.' artifact hash and size');
  if($e==='products'){
   $old=$job['sha256'];$again=$finish($w,$start($w,$profile));$assert($again['sha256']===$old,'Repeated CSV is identical with unchanged catalog');
   $third=$finish($w,$start($w,$profile));$assert($w->job($id)['artifact']===null && $third['artifact']!==null,'Retention removes old file but retains history');
  }
 }
 foreach(['xlsx','yml'] as $format){$profile=$make('products',$format);$j=$finish($w,$start($w,$profile));$assert($j['state']==='done',$format.' completed '.($j['error']??''));$content=$read($j);
  if($format==='xlsx'){$path=tempnam(sys_get_temp_dir(),'export-qa-');file_put_contents($path,$content);$zip=new ZipArchive();$assert($zip->open($path)===true,'XLSX ZIP opens');$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$xml=simplexml_load_string($sheet);$assert($xml!==false && !str_contains($sheet,'<f>'),'XLSX valid sheet without formula cells');$zip->close();unlink($path);}
  else{$xml=simplexml_load_string($content);$assert($xml!==false && count($xml->shop->offers->offer)===(int)$j['written'],'YML valid XML and offer count');$assert((int)$j['skipped']+(int)$j['written']===(int)$j['processed'],'YML rejected offers accounted for');}
 }
 $f=fopen('php://temp','w+b');$csv=new Csv();$c=['columns'=>[['key'=>'value','header'=>'=heading']],'settings'=>['csv_separator'=>';','csv_bom'=>0,'list_separator'=>' | ']];
 $csv->head($f,$c);$csv->rows($f,[['value'=>"\t=SUM(1;2)"],['value'=>'@cmd'],['value'=>"line1\nline2"]],$c);rewind($f);$formula=stream_get_contents($f);fclose($f);$assert(str_contains($formula,"'=heading")&&str_contains($formula,"'@cmd")&&str_contains($formula,"'\t=SUM"),'CSV escapes formulas and leading whitespace');
 try{new Files(['folder'=>'../escape']);$assert(false,'Path traversal rejected');}catch(InvalidArgumentException){$assert(true,'Path traversal rejected');}
 // Crash replay and lock handling on a separate profile.
 $p=$make('products');$id=$start($w,$p);$job=$w->job($id);$files=new Files($job['snapshot']['storage']);$files->write($id,'part-0.php',fn($s)=>Cells::put($s,'interrupted'));
 $run("UPDATE #__dharmashop_export_jobs SET lease='another',lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE id=".$id);
 $assert((int)$w->step($id)['processed']===0,'Busy job does not advance');
 try{$w->change($id,'cancel');$assert(false,'Busy job cancellation refused');}catch(RuntimeException){$assert(true,'Busy job cancellation refused');}
 $run('UPDATE #__dharmashop_export_jobs SET lease=NULL,lease_until=NULL WHERE id='.$id);
 $j=$finish($w,$id);$assert(!str_contains($read($j),'interrupted'),'Replayed batch overwrites orphan partial file');
 $cancel=$start($w,$p);$w->change($cancel,'cancel');$assert($w->job($cancel)['state']==='cancelled','Cancel releases active profile');
 // Failure and resume using a temporary event listener; only isolated data/files are touched.
 $fail=true;$app->getDispatcher()->addListener('onDharmaShopExportFormatsCollect',$listener=static function($event)use(&$fail){$formats=$event->getArgument('formats');$formats['fault']=['label'=>'QA failure','writer'=>new class($fail) implements \Dharma\Component\DharmaShopExport\Administrator\Export\FormatInterface {
  private Csv $csv;private $fail;public function __construct(&$fail){$this->fail=&$fail;$this->csv=new Csv();}public function extension():string{return 'csv';}public function supports(string $e):bool{return true;}public function required(array $s):array{return [];}
  public function head($s,array $c):void{$this->csv->head($s,$c);}public function rows($s,array $rows,array $c):int{if($this->fail){Cells::put($s,'broken');throw new RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}return $this->csv->rows($s,$rows,$c);}public function tail($s,array $c):void{}public function seal(string $s,string $d,array $c):void{$this->csv->seal($s,$d,$c);}
 }];$event->setArgument('formats',$formats);});
 $fault=$start($w,$make('products','fault'));$j=$w->step($fault);$assert($j['state']==='failed'&&(int)$j['cursor']===0,'Failed write preserves durable cursor');$fail=false;$w->change($fault,'resume');$j=$finish($w,$fault);$assert($j['state']==='done'&&!str_contains($read($j),'broken'),'Resume completes without partial or duplicate rows');$app->getDispatcher()->removeListener('onDharmaShopExportFormatsCollect',$listener);
 // 100k synthetic catalog using own isolated tables. Source shop is untouched.
 foreach(['dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media'] as $suffix){$run('TRUNCATE TABLE #__'.$suffix);}
 $sequence=$prefix.'sequence';$run('CREATE TABLE '.$sequence.' (n int unsigned PRIMARY KEY)');$tables[]=$sequence;
 $digit='(SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
 $run('INSERT INTO '.$sequence.' SELECT a.d+10*b.d+100*c.d+1000*d.d+10000*e.d+1 FROM '.$digit.' a CROSS JOIN '.$digit.' b CROSS JOIN '.$digit.' c CROSS JOIN '.$digit.' d CROSS JOIN '.$digit.' e');
 $cat=(int)$original->setQuery("SELECT id FROM #__categories WHERE extension='com_dharmashop' AND published=1 AND parent_id>0 ORDER BY id",0,1)->loadResult();
 $run("INSERT INTO #__dharmashop_products (id,catid,title,alias,state,price_min,created) SELECT n,".$cat.",CONCAT('Scale ',n),CONCAT('scale-',n),1,10,UTC_TIMESTAMP() FROM ".$sequence);
 $run("INSERT INTO #__dharmashop_variants (id,product_id,sku,created) SELECT n,n,CONCAT('S-',n),UTC_TIMESTAMP() FROM ".$sequence);
 $run("INSERT INTO #__dharmashop_product_categories (catid,product_id,direct,is_main,state,in_stock,created,title_sort) SELECT ".$cat.",n,1,1,1,1,UTC_TIMESTAMP(),'Scale' FROM ".$sequence);
 $profile=$make('products');$id=$start($w,$profile,['batch'=>1000,'keep'=>2]);$startTime=microtime(true);$job=$finish($w,$id);$report['100k']=['state'=>$job['state'],'processed'=>(int)$job['processed'],'written'=>(int)$job['written'],'seconds'=>round(microtime(true)-$startTime,3),'peak_memory_mb'=>round(memory_get_peak_usage(true)/1048576,1),'bytes'=>(int)$job['bytes'],'segments'=>(int)$job['segments']];
 $assert($job['state']==='done'&&(int)$job['written']===100000,'100k streaming export completes');
 $s=(new Files($job['snapshot']['storage']))->stream($id,'artifact.php');$lines=0;while(fgetcsv($s,0,';','"','')!==false){$lines++;}fclose($s);$assert($lines===100001,'100k file contains exactly one header and 100k rows');
 $plan=$db->setQuery('EXPLAIN SELECT id FROM #__dharmashop_products WHERE id>99000 AND id<=100000 AND state>=0 ORDER BY id LIMIT 1000')->loadAssocList();$assert($plan[0]['key']==='PRIMARY'&&$plan[0]['type']==='range','Keyset batch uses primary index range');
 echo json_encode(['checks'=>$checks,'scale'=>$report],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
 file_put_contents(__DIR__.'/../dist/export-qa.json',json_encode(['checks'=>$checks,'scale'=>$report],JSON_PRETTY_PRINT));
}finally{
 foreach(array_reverse($tables) as $table){$run('DROP TABLE IF EXISTS '.$db->quoteName($table));}
 foreach(array_keys($folders) as $folder){$path=JPATH_ROOT.'/'.$folder;if(!str_starts_with($folder,'files/'.$tag)){throw new RuntimeException('Unexpected QA directory');}if(is_dir($path)){\Joomla\Filesystem\Folder::delete($path);}}
 echo "Removed only own isolated tables and QA artifacts.\n";
}
