<?php
/**
 * Регрессионные проверки правок по docs/REVIEW_CLAUDE.md на изолированных таблицах со случайным префиксом (магазин не изменяется):
 * остаток→наличие, пакетный перенос, границы чисел, кэш порядка групп. Использование: php dev/bench/review-checks.php [число товаров, по умолчанию 3000]
 */
declare(strict_types=1);
require __DIR__ . '/../boot.php';
use Joomla\CMS\Factory;
use Joomla\Database\{DatabaseInterface, DatabaseFactory};
use Joomla\Registry\Registry;
use Dharma\Component\DharmaShop\Administrator\Filter\{FilterCache, FilterDefinitionLoader, FacetIndexer};
$db=Factory::getContainer()->get(DatabaseInterface::class);
$app=Factory::getContainer()->get(Joomla\CMS\Application\SiteApplication::class); Factory::$application=$app;
$app->loadIdentity(new Joomla\CMS\User\User());
$input=$app->getInput();$input->set('option','com_dharmashop');$input->set('view','category');$input->set('id',51);
$component=$app->bootComponent('com_dharmashop');$config=Factory::getConfig();
$prefix='dffqa_'.bin2hex(random_bytes(4)).'_';
$bench=(new DatabaseFactory())->getDriver('mysqli',['host'=>$config->get('host'),'user'=>$config->get('user'),'password'=>$config->get('password'),'database'=>$config->get('db'),'prefix'=>$prefix,'select'=>true]);
$fixturePath=__DIR__.'/../../dist/common-fields-demo.json';
$fixture=is_file($fixturePath)?json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR):[];
$templateId=(int)(getenv('DHARMA_BENCH_PRODUCT')?:($fixture['product_ids'][0]??0));
if (!$templateId) { $templateId=(int)$db->setQuery('SELECT p.id FROM #__dharmashop_products p INNER JOIN #__dharmashop_product_groups g ON g.id=p.group_id WHERE p.state=1 AND g.state=1 ORDER BY p.id DESC LIMIT 1')->loadResult(); }
$template=$db->setQuery('SELECT id,group_id,catid FROM #__dharmashop_products WHERE id='.$templateId)->loadObject();
if(!$template || !$template->group_id)throw new RuntimeException('Missing fully populated demo fixture');
$catid=(int)$template->catid; $input->set('id',$catid);
$tables=[];$report=['date'=>date('c'),'sizes'=>[]];
$run=static function(string $sql)use($bench):void{$bench->setQuery($sql)->execute();};
$make=static function(string $suffix,bool $copy=false)use($bench,$db,$prefix,&$tables,$run):void{
 $table=$prefix.$suffix;$tables[]=$table;$run('CREATE TABLE '.$bench->quoteName($table).' LIKE '.$db->quoteName($db->getPrefix().$suffix));
 if($copy)$run('INSERT INTO '.$bench->quoteName($table).' SELECT * FROM '.$db->quoteName($db->getPrefix().$suffix));
};
$model=static function(array $filter,string $policy,int $scope)use($app,$component,$bench):object{
 $app->getInput()->set('filter',$filter);
 $m=$component->getMVCFactory()->createModel('Category','Site',['ignore_request'=>true]);$m->setDatabase($bench);$m->forCategory($scope);$m->setFilterModule(new Registry(['filtered_listing'=>$policy]));return $m;
};
$measure=static function(callable $call,int $samples=7):array{
 $times=[];for($i=0;$i<$samples+1;$i++){ $t=hrtime(true);$call();$ms=(hrtime(true)-$t)/1e6;if($i)$times[]=$ms; }
 sort($times);return ['p50_ms'=>round($times[(int)floor(count($times)/2)],2),'p95_ms'=>round($times[(int)ceil(count($times)*.95)-1],2)];
};
try {
 foreach(['categories','dharmashop_fields','dharmashop_fieldgroups','dharmashop_field_options','dharmashop_category_fields'] as $suffix)$make($suffix,true);
 $dynamic=['dharmashop_products','dharmashop_variants','dharmashop_variant_prices','dharmashop_product_categories','dharmashop_field_values','dharmashop_field_texts','dharmashop_field_media','dharmashop_category_field_values','dharmashop_category_field_texts','dharmashop_category_field_media','dharmashop_product_groups','dharmashop_group_categories','dharmashop_group_axes','dharmashop_group_media','dharmashop_filter_facets','dharmashop_filter_ranges'];
 foreach($dynamic as $suffix)$make($suffix);
 // The known fixture uses only public categories. Keep the isolated tree's access uniform.
 $run('UPDATE #__categories SET access=1,published=1 WHERE extension LIKE '.$bench->quote('com_dharmashop%'));
 $seq=$prefix.'seq';$tables[]=$seq;$run('CREATE TABLE '.$seq.' (n INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
 $digits='(SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
 $run('INSERT INTO '.$seq.' SELECT 1+a.d+10*b.d+100*c.d+1000*d.d+10000*e.d FROM '.$digits.' a CROSS JOIN '.$digits.' b CROSS JOIN '.$digits.' c CROSS JOIN '.$digits.' d CROSS JOIN '.$digits.' e');
 $definition=(new FilterDefinitionLoader($bench))->forCategory($catid,[1]);
 $color=$definition->fieldByAlias('fdemo-color');$memory=$definition->fieldByAlias('fdemo-memory');$features=$definition->fieldByAlias('fdemo-features');$weight=$definition->fieldByAlias('fdemo-weight');$diag=$definition->fieldByAlias('fdemo-diag');
 if(!$color||!$memory||!$features||!$weight||!$diag)throw new RuntimeException('Required filter fixture fields missing');
 $colorOptions=array_values($color->options);$memoryOptions=array_values($memory->options);$featureOptions=array_values($features->options);$brands=array_keys($definition->brands);
 foreach([isset($argv[1]) ? (int)$argv[1] : 3000] as $n){
  foreach($dynamic as $suffix)$run('TRUNCATE TABLE #__'.$suffix);
  $generate=hrtime(true);
  // Copy typed product and variant structure, replacing identifiers and values deterministically.
  foreach(['dharmashop_products','dharmashop_variants','dharmashop_variant_prices'] as $suffix){
   $insert=[];$select=[];
   foreach(array_keys($db->getTableColumns('#__'.$suffix)) as $col){
    if($col==='id' && $suffix==='dharmashop_variant_prices')continue;
    $insert[]=$bench->quoteName($col);
    $select[]=match(true){
     $col==='id'||$col==='product_id'||$col==='variant_id'=>'s.n',
     $col==='combo_hash'=>"SHA2(CONCAT('qa-',s.n),256)",$col==='alias'=>"CONCAT('qa-',s.n)",$col==='sku'=>"CONCAT('QAF-',s.n)",
     $col==='title'=>"CONCAT('QA ',s.n)",$col==='group_id'=>'CASE WHEN s.n%5=0 THEN NULL ELSE CEIL(s.n/5) END',
     $col==='group_ordering'=>'s.n%5',$col==='state'=>'1',$col==='on_sale'=>'IF(s.n%10=0,1,0)',
     in_array($col,['price_min','price_max','price_final'],true)=>'100+s.n%10000',
     $col==='price'=>'IF(s.n%10=0,200,100)+s.n%10000',
     in_array($col,['qty','qty_available'],true)=>'IF(s.n%5=0,0,10)',
     default=>'t.'.$bench->quoteName($col)};
   }
   $where=match($suffix){'dharmashop_products'=>'t.id='.$templateId,'dharmashop_variants'=>'t.id=(SELECT id FROM '.$db->getPrefix().'dharmashop_variants WHERE product_id='.$templateId.' ORDER BY id LIMIT 1)',default=>'t.variant_id=(SELECT id FROM '.$db->getPrefix().'dharmashop_variants WHERE product_id='.$templateId.' LIMIT 1)'};
   $run('INSERT INTO #__'.$suffix.' ('.implode(',',$insert).') SELECT '.implode(',',$select).' FROM '.$db->getPrefix().$suffix.' t CROSS JOIN '.$seq.' s WHERE s.n<='.$n.' AND '.$where);
  }
  // Main category and ancestors from the template; brand depends on n/7, independent of color and memory.
  $pcInsert=[];$pcSelect=[];
  foreach(array_keys($db->getTableColumns('#__dharmashop_product_categories')) as $col){
   $pcInsert[]=$bench->quoteName($col);
   $pcSelect[]=match($col){'product_id'=>'s.n','price_sort'=>'100+s.n%10000','in_stock'=>'IF(s.n%5=0,0,1)',default=>'t.'.$bench->quoteName($col)};
  }
  $run('INSERT INTO #__dharmashop_product_categories ('.implode(',',$pcInsert).') SELECT '.implode(',',$pcSelect).' FROM '.$db->getPrefix().'dharmashop_product_categories t JOIN '.$db->getPrefix().'categories c ON c.id=t.catid CROSS JOIN '.$seq.' s WHERE t.product_id='.$templateId.' AND c.extension='.$db->quote('com_dharmashop').' AND s.n<='.$n);
  foreach($brands as $i=>$brand){
   $run('INSERT INTO #__dharmashop_product_categories (product_id,catid,direct,is_main,state,sort_order,price_sort,in_stock,created,title_sort) SELECT n,'.(int)$brand.',1,0,1,n,100+n%10000,IF(n%5=0,0,1),NOW(),CONCAT("qa ",n) FROM '.$seq.' WHERE n<='.$n.' AND FLOOR((n-1)/7)%'.count($brands).'='.$i);
  }
  foreach([[$color,$colorOptions,'(s.n-1)%'.count($colorOptions)],[$memory,$memoryOptions,'FLOOR((s.n-1)/5)%'.count($memoryOptions)],[$features,$featureOptions,'(s.n-1)%'.count($featureOptions)]] as [$field,$options,$formula]){
   foreach($options as $i=>$option)$run('INSERT INTO #__dharmashop_field_values (field_id,product_id,variant_id,option_id) SELECT '.$field->id.',s.n,NULL,'.$option->id.' FROM '.$seq.' s WHERE s.n<='.$n.' AND '.$formula.'='.$i);
  }
  foreach([[$weight,'100+s.n%151'],[$diag,'5+(s.n%19)/10']] as [$field,$formula])$run('INSERT INTO #__dharmashop_field_values (field_id,product_id,variant_id,num) SELECT '.$field->id.',s.n,NULL,'.$formula.' FROM '.$seq.' s WHERE s.n<='.$n);
  // Four group members plus one independent product in each five-product block.
  foreach(['dharmashop_product_groups','dharmashop_group_categories','dharmashop_group_axes'] as $suffix){
   $insert=[];$select=[];
   foreach(array_keys($db->getTableColumns('#__'.$suffix)) as $col){
    if($col==='id' && $suffix!=='dharmashop_product_groups')continue;
    $insert[]=$bench->quoteName($col);$select[]=match(true){$col==='id'||$col==='group_id'=>'s.n',$col==='alias'=>"CONCAT('qag-',s.n)",$col==='title'=>"CONCAT('QA Group ',s.n)",$col==='state'=>'1',default=>'t.'.$bench->quoteName($col)};
   }
   $where=($suffix==='dharmashop_product_groups'?'t.id=':'t.group_id=').(int)$template->group_id;
   $run('INSERT INTO #__'.$suffix.' ('.implode(',',$insert).') SELECT '.implode(',',$select).' FROM '.$db->getPrefix().$suffix.' t CROSS JOIN '.$seq.' s WHERE s.n<='.intdiv($n,5).' AND '.$where);
  }
  foreach($dynamic as $suffix){$bench->setQuery('ANALYZE TABLE #__'.$suffix)->loadAssocList();}
  $indexer=new FacetIndexer($bench);$indexer->rebuildAll();$bad=$indexer->verify(10000);if($bad)throw new RuntimeException('Precalculation mismatch: '.json_encode(array_slice($bad,0,3)));
  $report['sizes'][$n]=['generate_seconds'=>round((hrtime(true)-$generate)/1e9,2),'value_rows'=>(int)$bench->setQuery('SELECT COUNT(*) FROM #__dharmashop_field_values')->loadResult(),'verify'=>'pass','cases'=>[]];
  // ---- Проверки правок по REVIEW_CLAUDE.md (A1 остаток, A3 пакетные операции, A4 границы чисел, кэш порядка групп) ----
  foreach(['stock_levels','stock_movements','stock_locations','search'] as $s){ $make('dharmashop_'.$s); }
  $fi=new FacetIndexer($bench); $idx=new Dharma\Component\DharmaShop\Administrator\Catalog\Indexer($bench);
  $cp=Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop');
  $Cat=Dharma\Component\DharmaShop\Site\Model\CategoryModel::class;
  $fail=0; $pass=0;
  $check=function(string $name,bool $ok)use(&$fail,&$pass){ echo ($ok?'PASS ':'FAIL ').$name."\n"; $ok?++$pass:++$fail; };
  $mk=function(array $filter,string $policy,int $scope)use($model,$cp){$m=$model($filter,$policy,$scope);$cp->set('category_listing_mode',$policy==='products'?'products':'groups_products');return $m;};
  $check('baseline: предрасчёт совпадает с живым подсчётом',count($fi->verify(10000))===0);
  // A4: границы чисел
  $fid=(int)$weight->id;
  $mut=function(array $ids,string $sql,string $label)use($fi,$bench,$check){
    $t=$fi->begin($ids);$bench->setQuery($sql)->execute();$fi->end($t);
    $check("границы чисел: $label",count($fi->verify(10000))===0);
  };
  $mut([10],"UPDATE #__dharmashop_field_values SET num=99999 WHERE product_id=10 AND field_id=$fid",'новый максимум');
  $mut([10],"UPDATE #__dharmashop_field_values SET num=150 WHERE product_id=10 AND field_id=$fid",'максимум убран');
  $mut([11],"UPDATE #__dharmashop_field_values SET num=-5 WHERE product_id=11 AND field_id=$fid",'новый минимум');
  $mut([11],"UPDATE #__dharmashop_field_values SET num=120 WHERE product_id=11 AND field_id=$fid",'минимум убран');
  $mut([12],"UPDATE #__dharmashop_field_values SET num=num+0.5 WHERE product_id=12 AND field_id=$fid",'внутреннее значение');
  $mut([13],"DELETE FROM #__dharmashop_field_values WHERE product_id=13 AND field_id=$fid",'значение удалено');
  $mut([14],"UPDATE #__dharmashop_products SET state=0 WHERE id=14",'снятие с публикации');
  $mut([14],"UPDATE #__dharmashop_products SET state=1 WHERE id=14",'повторная публикация');
  // A3: пакетный перенос
  $target=(int)$bench->setQuery("SELECT id FROM #__categories WHERE extension='com_dharmashop' AND id<>$catid AND level>0 ORDER BY id LIMIT 1")->loadResult();
  $moved=[20,21,22,23,24,25];
  $t=$fi->begin($moved);
  foreach($moved as $pid){ $bench->setQuery("UPDATE #__dharmashop_products SET catid=$target WHERE id=$pid")->execute(); $idx->setCategories($pid,$target,[],0,[],'last',[]); }
  $fi->end($t);
  $check('пакетный перенос категории сопровождается предрасчётом',count($fi->verify(10000))===0);
  // Перенос большого числа товаров одной пересборкой
  $idx->rebuildProducts(range(1,1500));
  $check('rebuildProducts (>порога) оставляет предрасчёт согласованным',count($fi->verify(10000))===0);
  // A1: остаток
  $pid=40; $vid=(int)$bench->setQuery("SELECT id FROM #__dharmashop_variants WHERE product_id=$pid ORDER BY id LIMIT 1")->loadResult();
  $bench->setQuery("UPDATE #__dharmashop_variants SET stock_tracked=1,backorder=0,stock_total=1,in_stock=1 WHERE product_id=$pid")->execute();
  $bench->setQuery("UPDATE #__dharmashop_products SET in_stock=1 WHERE id=$pid")->execute();
  $bench->setQuery("UPDATE #__dharmashop_product_categories SET in_stock=1 WHERE product_id=$pid")->execute();
  $bench->setQuery("INSERT INTO #__dharmashop_stock_levels (variant_id,location_id,qty,reserved) VALUES ($vid,1,1,0) ON DUPLICATE KEY UPDATE qty=1,reserved=0")->execute();
  $stock=new Dharma\Component\DharmaShop\Administrator\Stock\StockService($bench);
  $stock->consume($vid,1.0,999999,null,false);
  $check('остаток: товар и строки витрины сняты с наличия',(int)$bench->setQuery("SELECT in_stock FROM #__dharmashop_products WHERE id=$pid")->loadResult()===0
     && (int)$bench->setQuery("SELECT COUNT(*) FROM #__dharmashop_product_categories WHERE product_id=$pid AND in_stock=1")->loadResult()===0);
  $app->getInput()->set('filter',['stock'=>'1']);
  $cp->set('category_listing_mode','products');
  $m=$component->getMVCFactory()->createModel('Category','Site',['ignore_request'=>true]);$m->setDatabase($bench);$m->forCategory($catid);
  $listed=array_map(fn($i)=>(int)$i->id,$m->getProducts('order',500,0));
  $check('остаток: фильтр «в наличии» не выдаёт проданный товар',!in_array($pid,$listed,true));
  $stock->restore($vid,1.0,999999,null);
  $check('остаток: возврат возвращает наличие товару',(int)$bench->setQuery("SELECT in_stock FROM #__dharmashop_products WHERE id=$pid")->loadResult()===1);
  // Кэш порядка групп = прямой запрос
  $app->getInput()->set('filter',[]);
  $diffs=0;
  foreach([0,$catid] as $scope){ foreach(['order','new','title','price_asc','price_desc'] as $sort){ foreach([0,24,48,240] as $start){
    $ids=[];$tot=[];
    foreach([5000,0] as $cap){ $Cat::$rankCap=$cap; FilterCache::forget(); $m=$mk([],'groups',$scope); $ids[$cap]=array_map(fn($i)=>(int)$i->id,$m->getProducts($sort,24,$start)); $tot[$cap]=$m->getTotal(); }
    if($ids[5000]!==$ids[0] || $tot[5000]!==$tot[0])++$diffs;
  }}}
  $Cat::$rankCap=5000;
  $check('кэш порядка групп даёт те же страницы и total, что прямой запрос',$diffs===0);
  echo "$pass проверок пройдено, $fail не пройдено\n";
  if($fail)exit(1);
 }
} finally {
 foreach(array_reverse($tables) as $table)$run('DROP TABLE IF EXISTS '.$bench->quoteName($table));
 echo "Own isolated tables removed.\n";
}
