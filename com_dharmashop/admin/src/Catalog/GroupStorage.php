<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Catalog;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** Аддитивная миграция; только установщик, не витрина. */
final class GroupStorage
{
 public static function upgrade(DatabaseInterface $db): void
 {
  $bad=(int)$db->setQuery('SELECT COUNT(*) FROM (SELECT p.id FROM #__dharmashop_products p LEFT JOIN #__dharmashop_variants v ON v.product_id=p.id GROUP BY p.id HAVING COUNT(v.id) <> 1) invalid')->loadResult();
  if ($bad) { throw new \RuntimeException('COM_DHARMASHOP_SKU_INVARIANT'); }
  foreach (explode(';',file_get_contents(__DIR__.'/../../sql/groups.mysql.utf8.sql')) as $ddl) { if(trim($ddl)!=='') { $db->setQuery($ddl)->execute(); } }
  $columns=$db->getTableColumns('#__dharmashop_products');
  foreach (['group_id'=>'int unsigned NULL','combo_hash'=>'char(64) CHARACTER SET ascii COLLATE ascii_bin NULL',
   'inherit_intro'=>"varchar(8) NOT NULL DEFAULT 'own'",'inherit_full'=>"varchar(8) NOT NULL DEFAULT 'own'",'inherit_images'=>"varchar(8) NOT NULL DEFAULT 'own'"] as $name=>$definition) {
   if(!isset($columns[$name])) { $db->setQuery('ALTER TABLE #__dharmashop_products ADD '.$db->quoteName($name).' '.$definition)->execute(); }
  }
  foreach (['#__dharmashop_products'=>['idx_group'=>'KEY idx_group (group_id,id)','uq_group_combo'=>'UNIQUE KEY uq_group_combo (group_id,combo_hash)'],
   '#__dharmashop_variants'=>['uq_product_sku'=>'UNIQUE KEY uq_product_sku (product_id)']] as $table=>$wanted) {
   $keys=[]; foreach($db->getTableKeys($table) as $key) {$keys[$key->Key_name]=true;}
   foreach($wanted as $name=>$definition) {if(!isset($keys[$name])) {$db->setQuery('ALTER TABLE '.$db->quoteName($table).' ADD '.$definition)->execute();}}
  }
 }
}
