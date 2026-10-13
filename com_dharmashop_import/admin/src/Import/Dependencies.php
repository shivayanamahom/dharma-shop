<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;

/** Read-only plans until resolve() is called by an approved applying job. */
final class Dependencies
{
    public function __construct(private DatabaseInterface $db) {}

    public function definition(array $mapping, array $profile, bool $optionsOnly = false): array
    {
        if(empty($profile['params'][$optionsOnly?'create_missing_options':'create_missing_fields'])||!in_array($profile['entity'],['products','categories'],true)){
            throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CREATE_FIELD_DISABLED');
        }
        $title=trim(strip_tags((string)($mapping['field_title']??'')));
        $alias=ApplicationHelper::stringURLSafe(trim((string)($mapping['field_alias']??''))?:$title);
        $type=(string)($mapping['field_type']??'text');
        if($title===''||mb_strlen($title)>255||$alias===''||mb_strlen($alias)>190||!in_array($type,['text','editor','number','range','checkbox','list'],true)){
            throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');
        }
        $user=Factory::getApplication()->getIdentity();
        if(!$user->authorise('dharmashop.fields.manage','com_dharmashop')||!$user->authorise($optionsOnly?'core.edit':'core.create','com_dharmashop')||(!$optionsOnly&&!$user->authorise('core.edit.state','com_dharmashop'))){
            throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);
        }
        $params=$type==='list'?['list_multiple'=>1,'multiple'=>1]:($type==='checkbox'?['checkbox_mode'=>'single']:[]);
        TypeRegistry::current()->get($type)->normalizeSettings($params);
        $data=['title'=>$title,'alias'=>$alias,'type'=>$type,'target'=>$profile['entity']==='products'?'product':'category','params'=>$params];
        $existing=$this->db->setQuery('SELECT id,target,type,state,all_categories,title,params FROM #__dharmashop_fields WHERE alias='.$this->db->quote($alias))->loadAssoc();
        if($existing&&($existing['type']!==$type||$existing['target']!==$data['target']||(int)$existing['state']!==1||$existing['title']!==$title)){
            throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FIELD_CONFLICT');
        }
        if($optionsOnly){if(!$existing||$type!=='list'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FIELD_CONFLICT');}$data['params']=json_decode($existing['params'],true)?:[];}
        return $data;
    }

    public function categories(string $value, array $profile): array
    {
        $separator=$profile['params']['category_separator'];
        $parts=array_map('trim',explode($separator,$value));
        if(!$parts||count($parts)>30||in_array('',$parts,true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}
        $chain=[];$parent=1;
        foreach($parts as $part){
            // Explicit readable title and alias also allow an exact round trip.
            $pair=explode('|||',$part,2);$title=trim($pair[0]);$alias=ApplicationHelper::stringURLSafe(trim($pair[1]??$title));
            if($title===''||mb_strlen($title)>255||$alias===''||mb_strlen($alias)>190){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}
            $ids=$parent?$this->db->setQuery("SELECT id,alias FROM #__categories WHERE extension='com_dharmashop' AND parent_id=".$parent.' AND (alias='.$this->db->quote($alias).' OR title='.$this->db->quote($title).')',0,2)->loadAssocList():[];
            if(count($ids)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CONFLICT');}
            $id=(int)($ids[0]['id']??0);if($id){$alias=$ids[0]['alias'];}
            if(!$id){
                if(empty($profile['params']['create_missing_categories'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}
                $u=Factory::getApplication()->getIdentity();$asset=$parent>1?'com_categories.category.'.$parent:'com_dharmashop';
                if(!$u->authorise('core.create',$asset)||!$u->authorise('core.edit.state',$asset)){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
            }
            $chain[]=['id'=>$id,'title'=>$title,'alias'=>$alias];$parent=$id;
        }
        return $chain;
    }

    private function model(string $name): object
    {
        return Factory::getApplication()->bootComponent('com_dharmashop')->getMVCFactory()->createModel($name,'Administrator',['ignore_request'=>true]);
    }

    public function resolve(array $profile, array $plan, Records $records): array
    {
        $patch=$plan['patch'];
        if(isset($patch['__category_chain'])){
            if(!(int)$this->db->setQuery("SELECT GET_LOCK('dharma.categories',10)")->loadResult()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
            try{
                $parent=1;
                foreach($patch['__category_chain'] as $category){
                    $current=$this->db->setQuery("SELECT id,alias,title FROM #__categories WHERE extension='com_dharmashop' AND parent_id=".$parent.' AND alias='.$this->db->quote($category['alias']),0,2)->loadAssocList();
                    if(count($current)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CONFLICT');}
                    if($category['id']&&(!$current||(int)$current[0]['id']!==(int)$category['id'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_STALE');}
                    if(!$current){
                        $u=Factory::getApplication()->getIdentity();$asset=$parent>1?'com_categories.category.'.$parent:'com_dharmashop';
                        if(!$u->authorise('core.create',$asset)||!$u->authorise('core.edit.state',$asset)){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
                        $model=$this->model('Category');
                        if(!$model->save(['id'=>0,'title'=>$category['title'],'alias'=>$category['alias'],'parent_id'=>$parent,'extension'=>'com_dharmashop','published'=>$profile['params']['new_state'],'params'=>[]])){throw new \InvalidArgumentException((string)$model->getError()?:'COM_DHARMASHOP_IMPORT_FAILURE');}
                        $parent=(int)$model->getState('category.id');
                    }else{
                        if(!$category['id']&&$current[0]['title']!==$category['title']){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FIELD_CONFLICT');}
                        $parent=(int)$current[0]['id'];
                    }
                }
                $patch['catid']=$parent;unset($patch['__category_chain']);
            }finally{$this->db->setQuery("DO RELEASE_LOCK('dharma.categories')")->execute();}
        }
        if(isset($patch['__new_fields'])){
            if(!(int)$this->db->setQuery("SELECT GET_LOCK('dharma.fields',10)")->loadResult()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
            try{
                foreach($patch['__new_fields'] as $alias=>$field){
                    $d=$this->definition(['field_title'=>$field['definition']['title'],'field_alias'=>$alias,'field_type'=>$field['definition']['type']],$profile,!empty($field['existing']));
                    $found=$this->db->setQuery('SELECT id FROM #__dharmashop_fields WHERE alias='.$this->db->quote($alias))->loadResult();
                    $model=$this->model('Field');
                    $values=$d['type']==='list'?$field['options']:[];$fieldId=(int)$found;
                    if(!$found){
                        $options=[];foreach($values as $value){$options[]=['title'=>$value,'alias'=>ApplicationHelper::stringURLSafe($value)];}
                        if(!$model->save($d+['id'=>0,'state'=>1,'access'=>1,'group_id'=>0,'all_categories'=>1,'required'=>0,'in_card'=>0,'in_page'=>1,'options'=>$options])){throw new \InvalidArgumentException((string)$model->getError()?:'COM_DHARMASHOP_IMPORT_FAILURE');}
                        $fieldId=(int)$model->getState('field.id');
                    }elseif($d['type']==='list'){
                        $current=(array)$model->getItem((int)$found);$options=$current['options'];$changed=false;
                        foreach($values as $value){$optionAlias=ApplicationHelper::stringURLSafe($value);$matches=array_filter($options,static fn($o)=>$o['alias']===$optionAlias||$o['title']===$value);if(count($matches)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}if(!$matches){$options[]=['id'=>0,'title'=>$value,'alias'=>$optionAlias];$changed=true;}}
                        if($changed&&!Factory::getApplication()->getIdentity()->authorise('core.edit','com_dharmashop')){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
                        if($changed&&!$model->save(array_replace($current,['options'=>$options]))){throw new \InvalidArgumentException((string)$model->getError()?:'COM_DHARMASHOP_IMPORT_FAILURE');}
                    }
                    $patch['custom_fields']['f_'.$fieldId]=$records->createdFieldValue($d,$fieldId,$field['value'],$profile['params']);
                }
                unset($patch['__new_fields']);
                $patch['custom_fields_present']=array_fill_keys(array_keys($patch['custom_fields']??[]),1);
            }finally{$this->db->setQuery("DO RELEASE_LOCK('dharma.fields')")->execute();}
        }
        $plan['patch']=$patch;return $plan;
    }
}
