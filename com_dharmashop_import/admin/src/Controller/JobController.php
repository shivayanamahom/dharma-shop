<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Controller;
use Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse;
use Dharma\Component\DharmaShopImport\Administrator\Import\{Workflow,Access};
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class JobController extends \Joomla\CMS\MVC\Controller\BaseController
{
    use RequiresActionTrait;
    protected $option='com_dharmashop_import';
    protected function requiredActions():array{return ['*'=>'core.manage'];}
    private function workflow():Workflow{return new Workflow(Factory::getContainer()->get(DatabaseInterface::class));}
    private function wantsJson():bool{return $this->input->get('format')==='json';}
    private function fail(\Throwable $e,int $status=400):void{BatchProgressResponse::send(['success'=>false,'error'=>Text::_(Workflow::message($e))],$status);}

    /** Состояние запуска для окна прогресса: фаза, счётчики, сводка проверки и доступные действия. */
    private function payload(array $job):array
    {
        $w=$this->workflow();$user=$this->app->getIdentity();$state=$job['state'];$settled=in_array($state,['checked','done','failed','cancelled'],true);
        $summary=$settled?$w->summary((int)$job['id']):null;
        $skip=!empty($job['snapshot']['profile']['params']['skip_errors']);
        return ['success'=>true,'id'=>(int)$job['id'],'state'=>$state,'processed'=>(int)$job['processed'],'total'=>(int)$job['total'],'errors'=>(int)$job['errors'],
            'phase'=>Text::_('COM_DHARMASHOP_IMPORT_PHASE_'.strtoupper($state)),'title'=>$job['snapshot']['profile']['title'],'error'=>$job['error']?Text::_($job['error']):'',
            'summary'=>$summary,'canApply'=>$state==='checked'&&$user->authorise('dharmashop.import.apply','com_dharmashop_import')&&($skip||!$job['errors']),
            'resumable'=>$state==='failed','url'=>'index.php?option='.$this->option.'&view=job&id='.(int)$job['id'],
            'errorsUrl'=>$summary&&$summary['errors']?'index.php?option='.$this->option.'&task=job.errors&id='.(int)$job['id'].'&'.Session::getFormToken().'=1':''];
    }

    public function upload():void{
        $this->checkToken('post');
        try{$data=$this->workflow()->upload($this->input->files->get('file',[],'array'));echo new JsonResponse($data);}
        catch(\Throwable $e){$this->app->setHeader('status','400',true);echo new JsonResponse(null,Text::_(Workflow::message($e)),true);}
        $this->app->close();
    }
    public function start():void{
        if($this->wantsJson()&&!Session::checkToken('post')){$this->fail(new \RuntimeException('JINVALID_TOKEN_NOTICE'),403);}
        if(!$this->wantsJson()){$this->checkToken('post');}
        try{
            $w=$this->workflow();$id=$w->start($this->input->post->getInt('id'));
            if($this->wantsJson()){BatchProgressResponse::send($this->payload($w->job($id)));}
            $this->setRedirect('index.php?option='.$this->option.'&view=job&run=1&id='.$id);
        }catch(\Throwable $e){
            if($this->wantsJson()){$this->fail($e);}
            $this->setMessage(Text::_(Workflow::message($e)),'error');$this->setRedirect('index.php?option='.$this->option.'&view=profiles');
        }
    }
    public function step():void{
        if($this->wantsJson()&&!Session::checkToken('post')){$this->fail(new \RuntimeException('JINVALID_TOKEN_NOTICE'),403);}
        if(!$this->wantsJson()){$this->checkToken('post');}
        $id=$this->input->post->getInt('id');
        try{
            $j=$this->workflow()->step($id);
            if($this->wantsJson()){BatchProgressResponse::send($this->payload($j));}
        }catch(\Throwable $e){
            if($this->wantsJson()){$this->fail($e);}
            $this->setMessage(Text::_(Workflow::message($e)),'error');
        }
        $this->setRedirect('index.php?option='.$this->option.'&view=job&run=1&id='.$id);
    }
    public function approve():void{$this->change('approve');}
    public function resume():void{$this->change('resume');}
    public function cancel():void{$this->change('cancel');}
    private function change(string $action):void{
        if($this->wantsJson()&&!Session::checkToken('post')){$this->fail(new \RuntimeException('JINVALID_TOKEN_NOTICE'),403);}
        if(!$this->wantsJson()){$this->checkToken('post');}
        $id=$this->input->post->getInt('id');
        try{
            $w=$this->workflow();$w->$action($id);
            if($this->wantsJson()){BatchProgressResponse::send($this->payload($w->job($id)));}
        }catch(\Throwable $e){
            if($this->wantsJson()){$this->fail($e);}
            $this->setMessage(Text::_(Workflow::message($e)),'error');
        }
        $this->setRedirect('index.php?option='.$this->option.'&view=job&run=1&id='.$id);
    }

    /** Отчёт об ошибках запуска в CSV: строка файла, ключ, причина и исходные ячейки. */
    public function errors():void{
        $this->checkToken('get');$id=$this->input->getInt('id');$w=$this->workflow();$job=$w->job($id);Access::job($job);
        while(ob_get_level()){ob_end_clean();}
        $this->app->setHeader('Content-Type','text/csv; charset=utf-8',true)->setHeader('Content-Disposition','attachment; filename="import-'.$id.'-errors.csv"',true)->setHeader('Cache-Control','private, no-store',true)->setHeader('X-Content-Type-Options','nosniff',true)->sendHeaders();
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,[Text::_('COM_DHARMASHOP_IMPORT_LINE'),Text::_('COM_DHARMASHOP_IMPORT_KEY'),Text::_('COM_DHARMASHOP_IMPORT_ERROR_REASON'),Text::_('COM_DHARMASHOP_IMPORT_SOURCE_CELLS')],';','"','');
        $after=0;
        do{
            $rows=$w->rows($id,$after,100,'error');
            foreach($rows as $row){
                $after=(int)$row['id'];$cells=json_decode((string)$row['payload'],true);$text=is_array($cells)&&array_is_list($cells)?implode(' | ',array_map('strval',$cells)):'';
                // Защита от внедрения формул: значения, начинающиеся с = + - @, получают апостроф.
                $cell=static fn(string $v):string=>preg_match('/^[\x00-\x20]*[=+@-]/',$v)?"'".$v:$v;
                fputcsv($out,[$row['line'],$cell((string)$row['source_key']),$cell(Text::_((string)$row['error'])),$cell($text)],';','"','');
            }
        }while(count($rows)===100);
        fclose($out);$this->app->close();
    }
}
