<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** Состояние процесса — в БД. Части с номером порции позволяют безопасный повтор после обрыва. */
final class Workflow {
 private Catalog $catalog;
 public function __construct(private DatabaseInterface $db){
  $app=Factory::getApplication();$language=$app->getLanguage();
  $tag=$app->isClient('administrator')?$language->getTag():(string)\Joomla\CMS\Component\ComponentHelper::getParams('com_languages')->get('administrator','en-GB');
  $language->load('com_dharmashop_export',JPATH_ADMINISTRATOR,'en-GB',true);$language->load('com_dharmashop_export',JPATH_ADMINISTRATOR,$tag,true);
  $this->catalog=new Catalog($db);
 }
 public function job(int $id):array {
  $row=$this->db->setQuery('SELECT id,profile_id,state,snapshot,`cursor`,ceiling,segments,total,processed,written,skipped,created,updated,finished,created_by,bytes,sha256,artifact,issues,error,lease,lease_until FROM #__dharmashop_export_jobs WHERE id='.(int)$id)->loadAssoc();
  if(!$row){throw new \RuntimeException('JERROR_LAYOUT_PAGE_NOT_FOUND',404);}
  $row['issues']=json_decode((string)$row['issues'],true)?:[];
  $row['snapshot']=json_decode($row['snapshot'],true,512,JSON_THROW_ON_ERROR);return $row;
 }
 public function profile(int $id):array {
  $p=$this->db->setQuery('SELECT id,title,state,entity,format,params FROM #__dharmashop_export_profiles WHERE id='.(int)$id)->loadAssoc();
  if(!$p){throw new \RuntimeException('JERROR_LAYOUT_PAGE_NOT_FOUND',404);}
  $p['params']=json_decode($p['params'],true)?:[];return $p;
 }
 public function preview(int $profile):array {
  $p=Plan::prepare($this->profile($profile),$this->catalog);$ceiling=$this->catalog->ceiling($p['entity']);
  return ['profile'=>$p,'total'=>$this->catalog->count($p,$ceiling),'rows'=>$this->catalog->page($p,0,$ceiling,10,array_column($p['params']['columns'],'key'))];
 }
 public function start(int $profile,int $user=0):int {
  $p=Plan::prepare($this->profile($profile),$this->catalog);if(!$p['state']){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_DISABLED');}
  $active=(int)$this->db->setQuery('SELECT id FROM #__dharmashop_export_jobs WHERE active_profile='.$profile)->loadResult();
  if($active){return $active;}
  $storage=Plan::storage();new Files($storage);$writer=(new Formats())->get($p['format']);
  $ceiling=$this->catalog->ceiling($p['entity']);
  $snapshot=['version'=>1,'profile'=>$p,'storage'=>$storage,'extension'=>$writer->extension()];
  $row=(object)['profile_id'=>$profile,'active_profile'=>$profile,'state'=>'running','snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'ceiling'=>$ceiling,'total'=>$this->catalog->count($p,$ceiling),'created'=>gmdate('Y-m-d H:i:s'),'updated'=>gmdate('Y-m-d H:i:s'),'created_by'=>$user];
  try{$this->db->insertObject('#__dharmashop_export_jobs',$row,'id');}catch(\Throwable $e){$active=(int)$this->db->setQuery('SELECT id FROM #__dharmashop_export_jobs WHERE active_profile='.$profile)->loadResult();if($active){return $active;}throw $e;}
  return (int)$row->id;
 }
 private function lease(int $id):?string {
  $token=bin2hex(random_bytes(24));
  Sql::exec($this->db,"UPDATE #__dharmashop_export_jobs SET lease=:token,lease_until=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 300 SECOND) WHERE id=".$id." AND state IN ('running','failed') AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP())",[':token'=>[$token]]);
  return $this->db->getAffectedRows()===1?$token:null;
 }
 private function save(int $id,string $token,array $values):void {
  $set=[];$bind=[':token'=>[$token]];$n=0;
  foreach($values as $key=>$value){$name=':v'.$n++;$set[]=$this->db->quoteName($key).' = '.($value===null?'NULL':$name);if($value!==null){$bind[$name]=[$value];}}
  Sql::exec($this->db,'UPDATE #__dharmashop_export_jobs SET '.implode(',',$set).' WHERE id='.$id.' AND lease=:token',$bind);
  if($this->db->getAffectedRows()!==1){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_BUSY');}
 }
 public function step(int $id):array {
  $job=$this->job($id);if($job['state']!=='running'){return $job;}$token=$this->lease($id);if(!$token){return $this->job($id);}
  try{
   $job=$this->job($id);$s=$job['snapshot'];$p=$s['profile'];$writer=(new Formats())->get($p['format']);
   if(!$writer->supports($p['entity'])){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FORMAT');}
   $files=new Files($s['storage']);$columns=$p['params']['columns'];$c=$this->catalog->context($p,$columns);
   $issues=$job['issues'];$c['issue']=static function(string $reason,int $row)use(&$issues){$issues[$reason]??=['count'=>0,'ids'=>[]];$issues[$reason]['count']++;if(count($issues[$reason]['ids'])<10){$issues[$reason]['ids'][]=$row;}};
   $c['profile_id']=$p['id'];$c['started']=$job['created'];$keys=array_unique(array_merge(array_column($columns,'key'),$writer->required($p['params'])));
   foreach($keys as $key){if(!isset($c['schema'][$key])){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_COLUMNS');}}
   $deadline=microtime(true)+$s['storage']['budget'];
   do {
    $rows=$this->catalog->page($p,(int)$job['cursor'],(int)$job['ceiling'],$s['storage']['batch'],$keys);
    if(!$rows){
     $c['processed']=(int)$job['processed'];
     $artifact=$files->assemble($id,(int)$job['segments'],$writer,$c);
     $this->save($id,$token,$artifact+['state'=>'done','active_profile'=>null,'finished'=>gmdate('Y-m-d H:i:s'),'updated'=>gmdate('Y-m-d H:i:s')]);
     $this->retain($p['id'],$s['storage']['keep']);break;
    }
    $c['processed']=(int)$job['processed'];$written=0;
    $files->write($id,'part-'.$job['segments'].'.php',function($stream)use($writer,$rows,$c,&$written){$written=$writer->rows($stream,$rows,$c);});
    $job['cursor']=(int)end($rows)['id'];$job['segments']++;$job['processed']+=count($rows);$job['written']+=$written;$job['skipped']+=count($rows)-$written;
    $this->save($id,$token,array_intersect_key($job,array_flip(['cursor','segments','processed','written','skipped']))+['updated'=>gmdate('Y-m-d H:i:s'),'issues'=>json_encode($issues,JSON_UNESCAPED_UNICODE)]);
   }while(microtime(true)<$deadline);
  }catch(\Throwable $e){
   Log::addLogger(['text_file'=>'com_dharmashop_export.log.php'],Log::ALL,['com_dharmashop_export']);
   Log::add(get_class($e).': '.$e->getMessage(),Log::ERROR,'com_dharmashop_export');
   $error=preg_match('/^COM_DHARMASHOP_EXPORT_[A-Z_]+$/D',$e->getMessage())?$e->getMessage():'COM_DHARMASHOP_EXPORT_FAILURE';
   $this->save($id,$token,['state'=>'failed','error'=>$error,'updated'=>gmdate('Y-m-d H:i:s')]);
  }finally{Sql::exec($this->db,'UPDATE #__dharmashop_export_jobs SET lease=NULL,lease_until=NULL WHERE id='.$id.' AND lease=:token',[':token'=>[$token]]);}
  return $this->job($id);
 }
 public function change(int $id,string $action):void {
  $job=$this->job($id);if(!in_array($job['state'],['running','failed'],true)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID');}
  $token=$this->lease($id);if(!$token){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_BUSY');}
  try{
   if($action==='resume'){$this->save($id,$token,['state'=>'running','error'=>null,'updated'=>gmdate('Y-m-d H:i:s')]);}
   elseif($action==='cancel'){$this->save($id,$token,['state'=>'cancelled','active_profile'=>null,'finished'=>gmdate('Y-m-d H:i:s'),'updated'=>gmdate('Y-m-d H:i:s')]);(new Files($job['snapshot']['storage']))->clean($id);}
   else{throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID');}
  }finally{Sql::exec($this->db,'UPDATE #__dharmashop_export_jobs SET lease=NULL,lease_until=NULL WHERE id='.$id.' AND lease=:token',[':token'=>[$token]]);}
 }
 /** Удаляет запуск и его файлы. Запуск, который выполняется прямо сейчас (действующая аренда), не удаляется. Публичный файл фида не затрагивается. */
 public function remove(int $id):bool {
  $job=$this->job($id);
  if($job['state']==='running'&&$job['lease_until']&&strtotime($job['lease_until'].' UTC')>time()){return false;}
  (new Files($job['snapshot']['storage']))->purge($id);
  $this->db->setQuery('DELETE FROM #__dharmashop_export_jobs WHERE id='.(int)$id)->execute();
  return true;
 }
 /** Очистка истории: удаляются завершённые и отменённые запуски; выполняющиеся и прерванные (их ещё можно продолжить) остаются. Возвращает число удалённых. */
 public function clear():int {
  $n=0;foreach($this->db->setQuery("SELECT id FROM #__dharmashop_export_jobs WHERE state IN ('done','cancelled') ORDER BY id")->loadColumn() as $id){if($this->remove((int)$id)){$n++;}}
  return $n;
 }
 private function retain(int $profile,int $keep):void {
  $cutoff=(int)$this->db->setQuery("SELECT id FROM #__dharmashop_export_jobs WHERE profile_id=".$profile." AND state='done' AND artifact IS NOT NULL ORDER BY id DESC",$keep-1,1)->loadResult();
  if(!$cutoff){return;}
  $jobs=$this->db->setQuery("SELECT id,snapshot FROM #__dharmashop_export_jobs WHERE profile_id=".$profile." AND state='done' AND artifact IS NOT NULL AND id<".$cutoff." ORDER BY id DESC",0,200)->loadAssocList();
  foreach($jobs as $j){$s=json_decode($j['snapshot'],true);(new Files($s['storage']))->clean((int)$j['id']);$this->db->setQuery('UPDATE #__dharmashop_export_jobs SET artifact=NULL WHERE id='.(int)$j['id'])->execute();}
 }
}
