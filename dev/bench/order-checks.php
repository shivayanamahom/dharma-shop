<?php
/**
 * Сквозная проверка заказа на ИЗОЛИРОВАННОЙ копии таблиц магазина (уведомления подменены, ничего не отправляется):
 * корзина, заказ с доставкой «самовывоз»/«стандартная» и оплатой sandbox/наличными, остаток, индекс групп, оплата, отмена с возвратом остатка.
 * Названия полей оформления, способы доставки (4, 5) и оплаты (1, 3) взяты из тестового сайта: при другой конфигурации поправьте значения.
 * Использование: php dev/bench/order-checks.php
 */
declare(strict_types=1);
require __DIR__ . '/../boot.php';
use Joomla\CMS\Factory; use Joomla\Database\{DatabaseInterface,DatabaseFactory}; use Joomla\Registry\Registry;
use Dharma\Component\DharmaShop\Administrator\Service\ShopServices;
use Dharma\Component\DharmaShop\Administrator\Notification\NotificationService;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupIndex;
$db=Factory::getContainer()->get(DatabaseInterface::class); $config=Factory::getConfig();
$app=Factory::getContainer()->get(Joomla\CMS\Application\SiteApplication::class); Factory::$application=$app; $app->loadIdentity(new Joomla\CMS\User\User());
$prefix='dsqa_'.bin2hex(random_bytes(3)).'_';
$bench=(new DatabaseFactory())->getDriver('mysqli',['host'=>$config->get('host'),'user'=>$config->get('user'),'password'=>$config->get('password'),'database'=>$config->get('db'),'prefix'=>$prefix,'select'=>true]);
$main=$db->getPrefix(); $created=[];
$fail=0;$pass=0; $check=function(string $n,bool $ok)use(&$fail,&$pass){echo ($ok?'PASS ':'FAIL ').$n."\n";$ok?++$pass:++$fail;};
try {
 foreach($db->setQuery("SHOW TABLES LIKE '".$main."dharmashop\\_%'")->loadColumn() as $t){ $suffix=substr($t,strlen($main)); $new=$prefix.$suffix; $created[]=$new;
   $bench->setQuery("CREATE TABLE `$new` LIKE `$t`")->execute(); $bench->setQuery("INSERT INTO `$new` SELECT * FROM `$t`")->execute(); }
 $new=$prefix.'categories'; $created[]=$new; $bench->setQuery("CREATE TABLE `$new` LIKE `{$main}categories`")->execute(); $bench->setQuery("INSERT INTO `$new` SELECT * FROM `{$main}categories`")->execute();
 $shop=new ShopServices($bench);
 // уведомления: сервис на основной БД не найдёт историю нового заказа и вернёт управление
 $prop=new ReflectionProperty(ShopServices::class,'notifications'); $prop->setAccessible(true); $prop->setValue($shop,new NotificationService($db));
 $settings=Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop');
 $gi=new GroupIndex($bench); $gi->rebuild(null);
 $check('база: индекс групп согласован',$gi->verify(100000)===0);
 // товары: участник группы с учётом остатка и обычный товар
 $member=(int)$bench->setQuery("SELECT p.id FROM #__dharmashop_products p WHERE p.group_id>0 AND p.state=1 ORDER BY p.id DESC LIMIT 1")->loadResult();
 $plain=(int)$bench->setQuery("SELECT p.id FROM #__dharmashop_products p WHERE (p.group_id IS NULL OR p.group_id=0) AND p.state=1 ORDER BY p.id DESC LIMIT 1")->loadResult();
 $var=fn(int $pid)=>(int)$bench->setQuery("SELECT id FROM #__dharmashop_variants WHERE product_id=$pid ORDER BY id LIMIT 1")->loadResult();
 foreach([[$member,3],[$plain,1]] as [$pid,$qty]){
   $bench->setQuery("UPDATE #__dharmashop_variants SET stock_tracked=1,backorder=0 WHERE product_id=$pid")->execute();
   $shop->stock()->setQty($var($pid),(float)$qty,'manual',null);
   $shop->rebuilder()->rebuild($pid);
 }
 $price=fn(int $pid)=>(float)$bench->setQuery("SELECT vp.price_final FROM #__dharmashop_variant_prices vp WHERE vp.variant_id=".$var($pid)." AND vp.price_list_id=1")->loadResult();
 $check('товары готовы к заказу (цена > 0)',$price($member)>0 && $price($plain)>0);
 $inStock=fn(int $pid)=>(int)$bench->setQuery("SELECT in_stock FROM #__dharmashop_products WHERE id=$pid")->loadResult();
 $check('до заказа оба товара в наличии',$inStock($member)===1 && $inStock($plain)===1);
 // корзина → заказ
 $cart=$shop->cart()->create(0);
 $r1=$shop->cart()->add($cart,$member,null,2.0); $r2=$shop->cart()->add($cart,$plain,null,1.0);
 $check('корзина: товары добавлены',($r1['ok']??false) && ($r2['ok']??false));
 $validated=Dharma\Component\DharmaShop\Administrator\Order\CheckoutFields::class;
 $validated=$validated::validate($settings,['name'=>'QA Test','imya'=>'QA Test','e_mail'=>'qa@example.test','telefon'=>'+79990000000'],['politika'=>'1']);
 echo "  validate errors: ".json_encode($validated['errors'])."\n";
 $key=md5('qa-'.microtime());
 $results=[];
 try{
   $res=$shop->orders()->create($cart,0,$key,$validated,$settings,['method'=>4,'input'=>[4=>['point'=>'1']]],['method'=>3,'input'=>[]]);
   $check('заказ создан (самовывоз + онлайн-оплата sandbox)',!empty($res['id'])); $orderId=(int)$res['id'];
 }catch(Throwable $e){ $check('заказ создан: '.get_class($e).' '.substr($e->getMessage(),0,160).(method_exists($e,'errors')?' '.json_encode($e->errors(),JSON_UNESCAPED_UNICODE):''),false); $orderId=0; }
 if($orderId){
   $check('остаток после заказа: участник группы и товар сняты',(int)$bench->setQuery("SELECT stock_total FROM #__dharmashop_variants WHERE product_id=$member")->loadResult()===1 && $inStock($plain)===0);
   $check('товар с нулевым остатком снят с наличия в витрине и фильтре',(int)$bench->setQuery("SELECT COUNT(*) FROM #__dharmashop_product_categories WHERE product_id=$plain AND in_stock=1")->loadResult()===0);
   $check('индекс групп согласован после заказа',$gi->verify(100000)===0);
   $check('записи заказа на месте (позиции, история, доставка, оплата)',
     (int)$bench->setQuery("SELECT COUNT(*) FROM #__dharmashop_order_items WHERE order_id=$orderId")->loadResult()===2
     && (int)$bench->setQuery("SELECT COUNT(*) FROM #__dharmashop_order_history WHERE order_id=$orderId")->loadResult()>=1
     && (int)$bench->setQuery("SELECT COUNT(*) FROM #__dharmashop_orders WHERE id=$orderId AND shipping_method_id=4 AND payment_method_id=3")->loadResult()===1);
   try{ $init=$shop->payments()->initiate($orderId); $check('оплата: initiate вернул способ продолжения ('.$init->kind.')',isset($init->kind)); }catch(Throwable $e){ $check('оплата initiate: '.get_class($e).' '.substr($e->getMessage(),0,140),false); }
   // отмена: статус «Отменён» возвращает остатки
   try{ $statusNew=(int)$bench->setQuery("SELECT status_id FROM #__dharmashop_orders WHERE id=$orderId")->loadResult();
        $changed=$shop->orderStatuses()->change($orderId,2,$statusNew,0);
        $check('смена статуса на «Отменён» выполнена',$changed===true);
        $check('возврат остатка: товар снова в наличии, склад вернулся',$inStock($plain)===1 && (int)$bench->setQuery("SELECT stock_total FROM #__dharmashop_variants WHERE product_id=$member")->loadResult()===3);
        $check('индекс групп согласован после отмены',$gi->verify(100000)===0);
   }catch(Throwable $e){ $check('смена статуса: '.get_class($e).' '.substr($e->getMessage(),0,140),false); }
 }
 // второй заказ: стандартная доставка + оплата наличными (вторая комбинация плагинов)
 $cart2=$shop->cart()->create(0); $shop->cart()->add($cart2,$member,null,1.0);
 try{ $res2=$shop->orders()->create($cart2,0,md5('qa2'.microtime()),$validated,$settings,['method'=>5,'input'=>[5=>['gorod'=>'Екатеринбург','ulitsa_i_dom'=>'ул. Ленина, 1']]],['method'=>1,'input'=>[]]);
      $total=(float)$bench->setQuery("SELECT total FROM #__dharmashop_orders WHERE id=".(int)$res2['id'])->loadResult();
      $check('заказ со стандартной доставкой и оплатой наличными создан (итого '.$total.')',$total>0);
 }catch(Throwable $e){ $check('второй заказ: '.get_class($e).' '.substr($e->getMessage(),0,140).(method_exists($e,'errors')?' '.json_encode($e->errors(),JSON_UNESCAPED_UNICODE):''),false); }
 echo "$pass проверок пройдено, $fail не пройдено\n";
} finally {
 foreach(array_reverse($created) as $t)$bench->setQuery("DROP TABLE IF EXISTS `$t`")->execute();
 echo "Own isolated tables removed.\n";
}
