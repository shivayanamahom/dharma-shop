<?php
/** Real model regression checks. --demo keeps one published, fully populated sample group. */
declare(strict_types=1);
require __DIR__ . '/boot.php';
define('JPATH_COMPONENT',JPATH_ADMINISTRATOR.'/components/com_dharmashop');
use Dharma\Component\DharmaShop\Administrator\Catalog\{GroupGenerator,GroupService};
use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
use Dharma\Component\DharmaShop\Administrator\Image\{ImageCleaner,ImageInventory};
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
$app=Factory::getContainer()->get(\Joomla\CMS\Application\AdministratorApplication::class);
Factory::$application=$app;
$app->getSession()->set('registry',new \Joomla\Registry\Registry());
$db=Factory::getContainer()->get(DatabaseInterface::class);
$username=getenv('DHARMA_USER') ?: throw new RuntimeException('Load dev/.env');
$app->loadIdentity(Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int)$db->setQuery('SELECT id FROM #__users WHERE username='.$db->quote($username))->loadResult()));
$factory=$app->bootComponent('com_dharmashop')->getMVCFactory();
$model=static fn($name)=>$factory->createModel($name,'Administrator',['ignore_request'=>true]);
$service=new GroupService($db);$generator=new GroupGenerator($db);$checks=0;
$check=static function(bool $ok,string $name) use (&$checks):void { if(!$ok)throw new RuntimeException('FAIL: '.$name);$checks++;echo "PASS: $name\n"; };
$save=static function(array $data) use ($model):int { $m=$model('Productgroup');if(!$m->save($data))throw new RuntimeException($m->getError());return(int)$m->getState('productgroup.id'); };
$update=static function(int $id,array $changes) use ($model,$save):void { $m=$model('Productgroup');$item=(array)$m->getItem($id);$save(array_replace($item,$changes)); };
$value=static fn(int $id)=>(new FieldValues($db))->values('product',['id'=>$id,'catid'=>51]);
$images=$db->setQuery('SELECT path FROM #__dharmashop_field_media WHERE product_id=205 AND field_id=0 ORDER BY ordering',0,3)->loadColumn();
if(count($images)<2)$images=$db->setQuery('SELECT DISTINCT path FROM #__dharmashop_field_media WHERE field_id=0',0,3)->loadColumn();
$file=$db->setQuery('SELECT path FROM #__dharmashop_field_media WHERE field_id=9',0,1)->loadResult();
$gallery=$db->setQuery('SELECT path FROM #__dharmashop_field_media WHERE field_id=10 ORDER BY id',0,2)->loadColumn();
$common=['f_1'=>[1,2],'f_2'=>'OLED 120','f_3'=>3,'f_4'=>'<p>Общие характеристики: OLED, 120 Гц, защита экрана.</p>',
'f_5'=>24,'f_6'=>['from'=>100,'to'=>300],'f_7'=>5,'f_8'=>'<p>Комплектация: смартфон, кабель, инструкция. Гарантия 24 месяца.</p>',
'f_9'=>[['path'=>$file,'title'=>'Документация']], 'f_10'=>array_map(static fn($p)=>['path'=>$p,'alt'=>'Дополнительное фото'],$gallery),
'f_11'=>[['source'=>'youtube','url'=>'https://www.youtube.com/watch?v=Pfgow_AEJLU','title'=>'Обзор']],
'f_101'=>101,'f_103'=>0,'f_104'=>0,'f_105'=>['from'=>6.1,'to'=>6.7]];
$ids=[];$demo=0;$success=false;
try {
 $data=['id'=>0,'title'=>'Демо: общие поля и варианты '.date('Ymd-His'),'alias'=>'','state'=>0,'catid'=>51,'extra_catids'=>[52],
 'brand_id'=>59,'badge_ids'=>[64,65],'axes'=>[102,106],'members'=>[],'copy_fields'=>1,
 'introtext'=>'<p>Демонстрационная группа: 3 объёма памяти × 2 цвета. Общие характеристики заполнены один раз.</p>',
 'fulltext'=>'<p>Все варианты — самостоятельные товары со своей ценой, остатком и URL. Общие поля копируются при генерации. Описания и основная галерея наследуются из группы.</p>',
 'gallery'=>array_map(static fn($p)=>['path'=>$p,'alt'=>'Демонстрационный смартфон'],$images),
 'title_template'=>'{group.title} · {variant.values}','custom_fields'=>$common,'custom_fields_present'=>array_fill_keys(array_keys($common),1)];
 $start=hrtime(true);$demo=$save($data);$ids[]=$demo;
 $stored=(new FieldValues($db,'group',[102,106]))->values('product',['id'=>$demo,'catid'=>51]);
 $check(count($stored)===15,'15 common fields saved across all 10 field types');
 $check((int)$stored['f_103']===0 && (float)$stored['f_104']===0.0,'false checkbox and numeric zero preserved');
 $check($stored['f_11'][0]['source']==='youtube','video decode can be encoded again without fetching provider data');
 $m=$model('Productgroup');$m->setState('productgroup.id',$demo);$form=$m->getForm();
 $check($form && !$form->getField('f_102','custom_fields') && !$form->getField('f_106','custom_fields'),'axes excluded from common-field form');
 $check($form->getField('f_9','custom_fields') && $form->getField('f_4','custom_fields'),'native fields available on group form');
 $batch=$data+['generation_values'=>['f_102'=>[111,112,113],'f_106'=>[125,126]],'generation_price'=>15990,'generation_stock'=>10];
 $draft=$generator->prepare($demo,$batch);$batch['executions']=$draft['rows'];
 foreach($batch['executions'] as $i=>&$row){$row['price']=15990+$i*1500;$row['stock_qty']=$i===5?0:10+$i;if($i%2)$row['image']=$images[1];}unset($row);
 $batch['revision']=(int)$service->get($demo)->revision;
 $result=$model('Productgroup')->createExecutions($demo,$batch);
 $check(count($result['created'])===6 && !$result['error'],'six published variants generated: '.$result['error']);
 $created=$result['created'];$elapsed=(hrtime(true)-$start)/1e6;
 foreach($created as $i=>$id){$v=$value($id);$check(count($v)===17 && $v['f_2']==='OLED 120' && count($v['f_10'])===2 && $v['f_11'][0]['source']==='youtube','variant '.($i+1).': own values, text, files, gallery and video');}
 $check((int)$db->setQuery('SELECT COUNT(*) FROM #__dharmashop_variants WHERE product_id IN ('.implode(',',$created).')')->loadResult()===6,'one service SKU per generated product');
 $check((int)$db->setQuery('SELECT COUNT(*) FROM #__dharmashop_group_field_values WHERE group_id='.$demo.' AND field_id IN(102,106)')->loadResult()===0,'axes never written to common storage');
 $check((int)$db->setQuery('SELECT COUNT(*) FROM #__dharmashop_product_categories WHERE product_id='.$created[0].' AND catid IN(51,52,59,64,65)')->loadResult()===5,'category, additional category, brand and badges copied');
 $p=$model('Product');$item=(array)$p->getItem($created[0]);$item['custom_fields']=['f_2'=>'Ручное'];$item['custom_fields_present']=['f_2'=>1];
 $check($p->save($item),'manual product field edit saved');
 $newCommon=$common;$newCommon['f_2']='Новый';$update($demo,['custom_fields'=>$newCommon,'custom_fields_present'=>array_fill_keys(array_keys($newCommon),1)]);
 $check($value($created[0])['f_2']==='Ручное' && $value($created[1])['f_2']==='OLED 120','saving group does not overwrite manual or generated snapshots');
 $p=$model('Product');$item=(array)$p->getItem($created[0]);$item['custom_fields']=['f_2'=>'OLED 120'];$item['custom_fields_present']=['f_2'=>1];$check($p->save($item),'demo field restored');
 $update($demo,['custom_fields'=>$common,'custom_fields_present'=>array_fill_keys(array_keys($common),1)]);
 $batch['revision']=(int)$service->get($demo)->revision;$repeat=$model('Productgroup')->createExecutions($demo,$batch);
 $check(!$repeat['created'] && $repeat['skipped']===6,'repeated generation skips six existing combinations');
 $bad=$common;$bad['f_5']=999;$m=$model('Productgroup');$item=(array)$m->getItem($demo);
 $check(!$m->save(array_replace($item,['custom_fields'=>$bad])),'invalid common numeric bound rejected before save');
 $check((new FieldValues($db,'group',[102,106]))->values('product',['id'=>$demo,'catid'=>51])['f_2']==='OLED 120','failed save leaves common values intact');
 $cleaner=new ImageCleaner($db,JPATH_ROOT);
 $check($cleaner->isReferenced($gallery[0]),'shared image protected by group and product references');
 $cursor=['phase'=>6,'id'=>0];$inventory=(new ImageInventory($db,new \Joomla\Registry\Registry()))->next($cursor);
 $check($inventory && count($inventory[0][1])===3,'resize inventory visits common gallery storage');
 // A variation-enabled color is common if this particular group only varies by memory.
 $off=$data;$off['title']='Проверка отключённого копирования '.bin2hex(random_bytes(3));$off['copy_fields']=0;$off['axes']=[102];$off['custom_fields']['f_106']=126;$off['custom_fields_present']['f_106']=1;
 $offId=$save($off);$ids[]=$offId;
 $offBatch=$off+['generation_values'=>['f_102'=>[111,112]],'generation_price'=>100,'generation_stock'=>1];$offBatch['executions']=$generator->prepare($offId,$offBatch)['rows'];$offBatch['revision']=(int)$service->get($offId)->revision;
 $planned=$generator->plan($offId,$offBatch);$check(!isset($planned['products'][0]['custom_fields']['f_2']),'copy switch off excludes group defaults');
 $update($offId,['copy_fields'=>1]);$offBatch['revision']=(int)$service->get($offId)->revision;$planned=$generator->plan($offId,$offBatch);
 $check((int)$planned['products'][0]['custom_fields']['f_106']===126,'variation-enabled non-axis color can be common');
 $check((int)$planned['products'][0]['custom_fields']['f_102']===111 && (int)$planned['products'][1]['custom_fields']['f_102']===112,'row axes take priority');
 $with=$model('Productgroup')->createExecutions($offId,$offBatch);$check(count($with['created'])===2 && !$with['error'],'generation with common variation-enabled color succeeds');
 // One own variant has a discount; another is unavailable, to exercise frontend/cart states.
 $p=$model('Product');$item=(array)$p->getItem($created[1]);$item['discount']='10%';$check($p->save($item),'demo discounted variant saved');
 $p=$model('Product');$item=(array)$p->getItem($created[5]);$item['stock_tracked']=1;$item['backorder']=0;
 $check($p->save($item) && (int)$db->setQuery('SELECT in_stock FROM #__dharmashop_variants WHERE product_id='.$created[5])->loadResult()===0,'zero stock blocks purchase when product tracking is enabled');
 $update($demo,['state'=>in_array('--demo',$argv,true)?1:0]);
 $manifest=['group_id'=>$demo,'product_ids'=>$created,'group_title'=>$data['title'],'generation_ms'=>round($elapsed,2),'checks'=>$checks];
 if(in_array('--demo',$argv,true))file_put_contents(__DIR__.'/../dist/common-fields-demo.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
 echo json_encode($manifest,JSON_UNESCAPED_UNICODE)."\n";$success=true;
} finally {
 foreach($ids as $id){if($success && $id===$demo && in_array('--demo',$argv,true))continue;$members=array_map(static fn($p)=>(int)$p->id,$service->members($id));if($members)$model('Product')->publish($members,-2);$one=[$id];$model('Productgroup')->publish($one,-2);}
}
