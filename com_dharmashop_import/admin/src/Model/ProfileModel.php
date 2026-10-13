<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Model;
use Dharma\Component\DharmaShopImport\Administrator\Import\{Profile,Schema,Workflow,CsvReader,Access};
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
\defined('_JEXEC') or die;
class ProfileModel extends AdminModel
{
    protected $option='com_dharmashop_import';
    protected $text_prefix='COM_DHARMASHOP_IMPORT';
    public $typeAlias='com_dharmashop_import.profile';
    public function getTable($name='Profile',$prefix='Administrator',$options=[]){return parent::getTable($name,$prefix,$options);}
    public function getItem($pk=null){
        $item=parent::getItem($pk);
        if($item){$item->params=is_string($item->params??null)?(json_decode($item->params,true)?:[]):(array)($item->params??[]);
            if(is_array($item->params['media_hosts']??null)){$item->params['media_hosts']=implode("\n",$item->params['media_hosts']);}
            if(!empty($item->params['source_id'])){
                try{$f=(new Workflow($this->getDatabase()))->file((int)$item->params['source_id']);$item->params['source_file']=[['path'=>$f['token'],'filename'=>$f['name'],'bytes'=>$f['bytes']]];}catch(\Throwable $e){$item->params['source_file']=[];}
            }
        }return $item;
    }
    protected function loadFormData():mixed{
        $data=Factory::getApplication()->getUserState($this->option.'.edit.profile.data',[])?:$this->getItem()?:[];
        if(is_object($data)&&empty($data->id)){$data->entity='categories';$data->format='csv';$data->state=1;}
        return $data;
    }
    public function getForm($data=[],$loadData=true):Form|false{
        $form=$this->loadForm($this->option.'.profile','profile',['control'=>'jform','load_data'=>$loadData]);
        if(!$form){return false;}
        if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option)){$form->setFieldAttribute('state','disabled','true');$form->setFieldAttribute('state','filter','unset');}
        return $form;
    }
    public function save($data){
        try{
            Access::require('dharmashop.import.check');
            $id=(int)($data['id']??0);$old=$id?(array)$this->getItem($id):[];$oldParams=(array)($old['params']??[]);
            if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option)){$data['state']=$id?(int)$old['state']:1;}
            $workflow=new Workflow($this->getDatabase());$p=(array)($data['params']??[]);
            if(($p['source_type']??'upload')==='upload'){
                $tokens=[];foreach((array)($p['source_file']??[]) as $item){if(!empty($item['path'])){$tokens[]=(string)$item['path'];}}
                if(count($tokens)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SIZE');}
                $p['source_id']=0;
                if($tokens){$source=(int)$this->getDatabase()->setQuery('SELECT id FROM #__dharmashop_import_files WHERE token='.$this->getDatabase()->quote($tokens[0])." AND kind='source'")->loadResult();$workflow->file($source);$p['source_id']=$source;}
            }
            unset($p['source_file']);$data['params']=$p;$data=Profile::prepare($data,$this->getDatabase());
            $hasSource=$data['params']['source_type']!=='upload'||$data['params']['source_id']>0;
            if($hasSource){
                $source=$workflow->receive($data);$sample=$workflow->sample($data,$source);if($data['format']==='xlsx'){$data['params']['xlsx_sheets']=$sample['sheets'];$data['params']['xlsx_sheet']=$data['params']['xlsx_sheet']?:($sample['sheets'][0]??'');}
                $changed=($oldParams['headers']??[])!==$sample['headers']||($old['entity']??'')!==$data['entity']||($old['format']??'')!==$data['format']||($oldParams['xlsx_sheet']??'')!==($data['params']['xlsx_sheet']??'');
                if($changed||!$data['params']['mapping']){
                    $schema=(new Schema($this->getDatabase()))->fields($data['entity']);$data['params']['mapping']=[];
                    $used=[];$auto=[];
                    foreach($sample['headers'] as $column=>$header){$target=Schema::automatic($header,$schema);$auto[$column]=$target;if($target!==''){$used[$target][]=$column;}}
                    // Одно поле получает одну колонку: из нескольких побеждает колонка с подписями (JSON), иначе первая.
                    foreach($used as $target=>$columns){if(count($columns)>1){$keep=$columns[0];foreach($columns as $c){if(stripos((string)$sample['headers'][$c],'json')!==false){$keep=$c;}}foreach($columns as $c){if($c!==$keep){$auto[$c]='';}}}}
                    foreach($sample['headers'] as $column=>$header){$data['params']['mapping'][]=['column'=>$column,'target'=>$auto[$column]];}
                }
                $data['params']['headers']=$sample['headers'];$data['params']['example']=$sample['example'];
            }else{$data['params']['headers']=[];$data['params']['example']=[];$data['params']['mapping']=[];}
            return parent::save($data);
        }catch(\Throwable $e){$this->setError(Text::_(Workflow::message($e)));return false;}
    }
    public function delete(&$pks){
        $ids=array_filter(array_map('intval',$pks));
        if($ids&&$this->getDatabase()->setQuery('SELECT id FROM #__dharmashop_import_jobs WHERE active_profile IN ('.implode(',',$ids).')',0,1)->loadResult()){$this->setError(Text::_('COM_DHARMASHOP_IMPORT_BUSY'));return false;}
        return parent::delete($pks);
    }
}
