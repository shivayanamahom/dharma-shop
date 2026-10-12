<?php
/** Read-only catalogue checks; only the explicit facets maintenance command writes derived counters. */
declare(strict_types=1);
require __DIR__ . '/boot.php';
if (realpath(JPATH_ROOT) !== '/Volumes/Work/Server/www/test') { throw new RuntimeException('Checks are restricted to test.test'); }
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Dharma\Component\DharmaShop\Administrator\Filter\{FilterState, FilterCache, FacetIndexer};
$app=Factory::getContainer()->get(Joomla\CMS\Application\SiteApplication::class);Factory::$application=$app;
$app->loadIdentity(new Joomla\CMS\User\User());$input=$app->getInput();$input->set('option','com_dharmashop');$input->set('view','category');
$db=Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);
$component=$app->bootComponent('com_dharmashop');$factory=$component->getMVCFactory();$passed=0;
$check=static function(bool $ok,string $label)use(&$passed):void{if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS $label\n";};
$make=static function(int $scope,array $filter,string $policy='groups',array $extra=[])use($input,$factory):object{
 $input->set('id',$scope);$input->set('filter',$filter);$m=$factory->createModel('Category','Site',['ignore_request'=>true]);$m->forCategory($scope);$m->setFilterModule(new Registry(['filtered_listing'=>$policy]+$extra));return $m;
};
foreach([0,51] as $scope){
 foreach([[],['brand'=>['google']],['f'=>['fdemo-memory'=>['128-gb']]]] as $filter){
  foreach(['order','new','price_asc','price_desc','title'] as $sort){
   $m=$make($scope,$filter);$direction=in_array($sort,['new','price_desc'],true)?'DESC':'ASC';
   $expr=(new ReflectionMethod($m,'groupSortExpression'))->invoke($m,$sort);
   $query=(new ReflectionMethod($m,'productsQuery'))->invoke($m,null,true);$query->select(['p.id','p.group_id',$expr.' AS sort_value']);
   $rows=$db->setQuery($query)->loadObjectList();
   usort($rows,static function(object $a,object $b)use($direction,$sort):int{
    if($a->sort_value===null || $b->sort_value===null){$cmp=($a->sort_value!==null)<=>($b->sort_value!==null);}
    elseif($sort==='title'){$cmp=strcasecmp($a->sort_value,$b->sort_value);}
    else{$cmp=$a->sort_value<=>$b->sort_value;}
    if(!$cmp)$cmp=(int)$a->id<=>(int)$b->id;return $direction==='ASC'?$cmp:-$cmp;
   });
   $expected=[];foreach($rows as $row){$unit=$row->group_id?-(int)$row->group_id:(int)$row->id;$expected[$unit]??=(int)$row->id;}
   $items=$m->getProducts($sort,24);$actual=array_map(static fn(object $p):int=>(int)$p->id,$items);
   $check($actual===array_slice(array_values($expected),0,24),"representatives $scope $sort ".json_encode($filter));
   $check($m->getTotal()===count($expected),"unit total $scope $sort");
  }
 }
}
$m=$make(0,[],'products');$def=$m->getFilterDefinition();
$check(FilterState::fromArray(['f'=>'bad','price'=>['min'=>['bad'],'max'=>'INF'],'brand'=>[['google']],'stock'=>['1']],$def)->isEmpty(),'malformed arrays and nonfinite bounds');
$bounded=FilterState::fromArray(['price'=>['min'=>'99999999999999999999','max'=>'-99999999999999999999']],$def);
$check($bounded->price===['min'=>-1.0E12,'max'=>1.0E12],'finite clamped and reordered bounds');
$check(FilterState::fromArray(['f'=>['unknown'=>['x']],'brand'=>['unknown']],$def)->isEmpty(),'unknown values discarded');
$check((new FilterState([2=>[5,3],1=>[8]],brands:[5,2]))->key()===(new FilterState([1=>[8],2=>[3,5]],brands:[2,5]))->key(),'cache key independent of selection order');
$m=$make(0,['brand'=>['google']],'products');$facets=$m->getFacets('live');$features=$m->getFilterDefinition()->fieldByAlias('fdemo-features');
$five=$features?->option('5g');$check($five && empty($facets['options'][$features->id][$five->id]),'Google excludes 5G');
$m=$make(0,['brand'=>['google'],'f'=>['fdemo-features'=>['5g']]],'products');$check($m->getTotal()===0,'direct incompatible URL remains honest empty result');
$m=$make(0,['brand'=>['google'],'f'=>['fdemo-features'=>['nfc','5g']]],'products',['fields_config'=>[['field'=>'fdemo-features','logic'=>'and']]]);
$check($m->getTotal()===0,'module AND logic enforced by SQL');
$m=$make(0,['brand'=>['google'],'f'=>['fdemo-features'=>['nfc','5g']]],'products',['fields_config'=>[['field'=>'fdemo-features','logic'=>'or']]]);
$check($m->getTotal()===4,'module OR logic enforced by SQL');
$m=$make(0,['brand'=>['google'],'f'=>['fdemo-features'=>['nfc']]],'products',['fields_config'=>[['field'=>'fdemo-features','logic'=>'and']]]);$f=$m->getFacets('live');
$check(empty($f['options'][$features->id][$five->id]),'AND cascade keeps current same-field restriction');
$m=$make(0,['sale'=>'1'],'products');$items=$m->getProducts('new',200);$ids=array_map(static fn(object $p):int=>(int)$p->id,$items);
$check(!$ids || !(int)$db->setQuery('SELECT COUNT(*) FROM #__dharmashop_products WHERE id IN ('.implode(',',$ids).') AND on_sale<>1')->loadResult(),'sale listing uses materialized flag');
$input->set('filter',[]);$cursor=0;$steps=0;
do{$step=$component->getServices()->maintenance()->run('facets',$cursor,50);$cursor=$step['cursor'];$steps++;if($steps>100)throw new RuntimeException('maintenance cursor');}while(!$step['done']);
$check((new FacetIndexer($db))->verify(10000)===[],'native maintenance facets and verify');
echo "$passed checks passed; maintenance steps=$steps\n";
