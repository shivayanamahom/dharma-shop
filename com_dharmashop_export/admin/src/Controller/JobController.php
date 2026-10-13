<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Controller;
use Dharma\Component\DharmaShopExport\Administrator\Export\{Workflow,Files,Access};
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class JobController extends \Joomla\CMS\MVC\Controller\BaseController {
 use RequiresActionTrait;
 /** Скачивание доступно и тем, у кого только отчёты: владение запуском проверяет Access::job. */
 protected function requiredActions():array {return ['*'=>'dharmashop.export.run','download'=>'core.manage'];}
 protected $option='com_dharmashop_export';
 private function workflow():Workflow {return new Workflow(Factory::getContainer()->get(DatabaseInterface::class));}
 /** Запрос окна прогресса магазина: короткий JSON вместо перенаправления (общий `BatchProgressResponse`). */
 private function wantsJson():bool {return $this->input->get('format')==='json';}
 private function send(array $payload,int $status=200):void {\Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse::send($payload,$status);}
 private function payload(array $job):array {
  $s=$job['snapshot'];$issues=[];foreach($job['issues'] as $reason=>$issue){$issues[]=Text::_($reason).': '.(int)$issue['count'].' · ID: '.implode(', ',$issue['ids']);}
  $done=$job['state']==='done';$public=$done&&($s['profile']['params']['visibility']??'')==='public'?Files::publicLink($s):'';
  return ['success'=>true,'id'=>(int)$job['id'],'state'=>$job['state'],'processed'=>(int)$job['processed'],'total'=>(int)$job['total'],'written'=>(int)$job['written'],'skipped'=>(int)$job['skipped'],
   'title'=>Text::_('COM_DHARMASHOP_EXPORT').': '.$s['profile']['title'],'done'=>$done,'error'=>$job['error']?Text::_($job['error']):'','issues'=>$issues,'bytes'=>(int)$job['bytes'],
   'download'=>$done&&$job['artifact']?'index.php?option='.$this->option.'&task=job.download&id='.(int)$job['id'].'&'.Session::getFormToken().'=1':'','public'=>$public];
 }
 public function start():void {
  if($this->wantsJson()&&!Session::checkToken('post')){$this->send(['success'=>false,'error'=>Text::_('JINVALID_TOKEN_NOTICE')],403);}
  if(!$this->wantsJson()){$this->checkToken('post');}
  $data=$this->input->post->get('jform',[],'array');$profile=(int)($data['id']??$this->input->post->getInt('id'));
  try{
   $workflow=$this->workflow();$id=$workflow->start($profile,(int)$this->app->getIdentity()->id);
   // Прерванный по ошибке запуск продолжается при повторном нажатии «Запустить».
   if($workflow->job($id)['state']==='failed'){$workflow->change($id,'resume');}
   if($this->wantsJson()){$this->send($this->payload($workflow->job($id)));}
   $this->setRedirect('index.php?option='.$this->option.'&view=job&id='.$id);
  }
  catch(\Throwable $e){
   if($this->wantsJson()){$this->send(['success'=>false,'error'=>Text::_(preg_match('/^COM_DHARMASHOP_EXPORT_[A-Z_]+$/D',$e->getMessage())?$e->getMessage():'COM_DHARMASHOP_EXPORT_FAILURE')],400);}
   $this->setMessage(Text::_($e->getMessage()),'error');$this->setRedirect('index.php?option='.$this->option.'&view=profiles');
  }
 }
 public function step():void {
  if($this->wantsJson()){
   if(!Session::checkToken('post')){$this->send(['success'=>false,'error'=>Text::_('JINVALID_TOKEN_NOTICE')],403);}
   $id=$this->input->post->getInt('id');Access::job($this->workflow()->job($id));$this->send($this->payload($this->workflow()->step($id)));
  }
  $this->checkToken('get');$id=$this->input->getInt('id');Access::job($this->workflow()->job($id));
  $this->workflow()->step($id);$this->setRedirect('index.php?option='.$this->option.'&view=job&id='.$id);
 }
 public function resume():void {$this->change('resume');}
 public function cancel():void {$this->change('cancel');}
 private function change(string $action):void {
  $this->checkToken('post');$id=$this->input->post->getInt('id');Access::job($this->workflow()->job($id));
  try{$this->workflow()->change($id,$action);}catch(\Throwable $e){$this->setMessage(Text::_($e->getMessage()),'error');}
  $this->setRedirect('index.php?option='.$this->option.'&view=job&id='.$id);
 }
 public function download():void {
  $this->checkToken('get');$job=$this->workflow()->job($this->input->getInt('id'));Access::job($job);
  if($job['state']!=='done'||!$job['artifact']){throw new \RuntimeException(Text::_('COM_DHARMASHOP_EXPORT_NO_FILE'),404);}
  $stream=(new Files($job['snapshot']['storage']))->stream((int)$job['id'],'artifact.php');$s=$job['snapshot'];$name=$s['profile']['params']['file_name'].'.'.$s['extension'];
  while(ob_get_level()){ob_end_clean();}
  $this->app->setHeader('Content-Type','application/octet-stream',true)->setHeader('Content-Disposition','attachment; filename="'.$name.'"',true)->setHeader('Content-Length',(string)$job['bytes'],true)->setHeader('Cache-Control','private, no-store',true)->setHeader('X-Content-Type-Options','nosniff',true)->sendHeaders();
  fpassthru($stream);fclose($stream);$this->app->close();
 }
}
