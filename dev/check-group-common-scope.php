<?php
/** Own unpublished fixtures for required fields and category isolation; kept in trash. */
declare(strict_types=1);
require __DIR__.'/boot.php';
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Dharma\Component\DharmaShop\Administrator\Catalog\{GroupService,GroupGenerator};
use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
$app=Factory::getContainer()->get(\Joomla\CMS\Application\AdministratorApplication::class);Factory::$application=$app;$app->getSession()->set('registry',new \Joomla\Registry\Registry());
$db=Factory::getContainer()->get(DatabaseInterface::class);$name=getenv('DHARMA_USER') ?: throw new RuntimeException('Load dev/.env');
$app->loadIdentity(Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int)$db->setQuery('SELECT id FROM #__users WHERE username='.$db->quote($name))->loadResult()));
$f=$app->bootComponent('com_dharmashop')->getMVCFactory();$m=static fn($n)=>$f->createModel($n,'Administrator',['ignore_request'=>true]);$groups=new GroupService($db);$gen=new GroupGenerator($db);
$catids=[];$fieldid=$gid=0;$n=0;$check=static function(bool $ok,string $s)use(&$n){if(!$ok)throw new RuntimeException('FAIL '.$s);$n++;echo "PASS: $s\n";};
try{
 foreach(['A','B'] as $label){$cat=$m('Category');$check($cat->save(['id'=>0,'extension'=>'com_dharmashop','parent_id'=>1,'title'=>'QA common scope '.$label.' '.bin2hex(random_bytes(3)),'published'=>0,'access'=>1,'params'=>[]]),'own category '.$label.': '.$cat->getError());$catids[]=(int)$cat->getState('category.id');}
 $field=$m('Field');$check($field->save(['id'=>0,'title'=>'QA required common '.bin2hex(random_bytes(3)),'target'=>'product','type'=>'text','state'=>1,'access'=>1,'required'=>1,'all_categories'=>0,'catids'=>[$catids[0]],'params'=>['text_maxlength'=>20]]),'own category-scoped required field: '.$field->getError());$fieldid=(int)$field->getState('field.id');$key='f_'.$fieldid;
 $data=['id'=>0,'title'=>'QA common scope '.bin2hex(random_bytes(3)),'catid'=>$catids[0],'state'=>0,'axes'=>[102,106],'members'=>[],'copy_fields'=>1,'custom_fields'=>[$key=>''],'custom_fields_present'=>[$key=>1]];
 $group=$m('Productgroup');$check($group->save($data),'group accepts empty required product field');$gid=(int)$group->getState('productgroup.id');
 $batch=$data+['generation_values'=>['f_102'=>[111],'f_106'=>[125]],'generation_price'=>10,'generation_stock'=>1];$batch['executions']=$gen->prepare($gid,$batch)['rows'];$batch['revision']=(int)$groups->get($gid)->revision;
 $rejected=false;try{$gen->plan($gid,$batch);}catch(InvalidArgumentException $e){$rejected=$e->getPrevious()?->getMessage()==='COM_DHARMASHOP_FIELD_VALUE_REQUIRED';}
 $check($rejected && !$groups->members($gid),'required product value rejected before first write');
 $group=$m('Productgroup');$item=(array)$group->getItem($gid);$item['custom_fields']=[$key=>'Гарантия'];$check($group->save($item),'required common value saved');
 $table=$m('Field')->getTable();$check($table->hasValues($fieldid),'field deletion guard sees group-only values');
 $batch['revision']=(int)$groups->get($gid)->revision;$plan=$gen->plan($gid,$batch);$check($plan['products'][0]['custom_fields'][$key]==='Гарантия','required common value copied in source category');
 $batch['generation_catid']=$catids[1];$plan=$gen->plan($gid,$batch);$check(!array_key_exists($key,$plan['products'][0]['custom_fields']),'generation category excludes inapplicable field');
 $group=$m('Productgroup');$item=(array)$group->getItem($gid);$item['catid']=$catids[1];unset($item['custom_fields'],$item['custom_fields_present']);$check($group->save($item),'category changed without field payload');
 $check((int)$db->setQuery('SELECT COUNT(*) FROM #__dharmashop_group_field_values WHERE group_id='.$gid.' AND field_id='.$fieldid)->loadResult()===1,'out-of-scope defaults preserved in storage');
 $check(!(new FieldValues($db,'group',[102,106]))->values('product',['id'=>$gid,'catid'=>$catids[1]]),'out-of-scope defaults excluded from copying');
 $group=$m('Productgroup');$item=(array)$group->getItem($gid);$item['catid']=$catids[0];$group->save($item);$batch['generation_catid']=$catids[0];$batch['revision']=(int)$groups->get($gid)->revision;
 $r=$m('Productgroup')->createExecutions($gid,$batch);$check(count($r['created'])===1 && !$r['error'],'required field survives native product save');
 $id=$r['created'][0];$v=(new FieldValues($db))->values('product',['id'=>$id,'catid'=>$catids[0]]);$check($v[$key]==='Гарантия','required snapshot stored on product');
 echo "$n scope checks passed\n";
}finally{
 if($gid){$ids=array_map(static fn($p)=>(int)$p->id,$groups->members($gid));if($ids)$m('Product')->publish($ids,-2);$ids=[$gid];$m('Productgroup')->publish($ids,-2);}
 if($fieldid){$ids=[$fieldid];$m('Field')->publish($ids,-2);}if($catids)$m('Category')->publish($catids,-2);
}
