<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Dharma\Component\DharmaShop\Administrator\Fields\{FieldValues,FileStorage,TypeRegistry};
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Translation into native model payloads. Only apply() may change the catalogue. */
final class Records
{
    private array $items = [];
    private array $schemas = [];
    private array $options = [];
    private array $taxonomy = [];

    public function __construct(private DatabaseInterface $db) {}

    /**
     * Приводит значение к виду, пригодному для сравнения «файл против карточки»: числа по значению (0.000 = 0), булевы как 0/1,
     * списки медиа сводятся к path/alt/title (остальные служебные ключи карточки не важны), словари сортируются.
     */
    private function shape(mixed $v): mixed
    {
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) {
            if (array_is_list($v) && $v && !array_filter($v, static fn ($i) => !is_array($i) || !array_key_exists('path', $i))) {
                return array_map(static fn ($i) => ['path' => (string) $i['path'], 'alt' => (string) ($i['alt'] ?? ''), 'title' => (string) ($i['title'] ?? '')], $v);
            }
            if (!array_is_list($v)) {
                ksort($v);
            }

            return array_map(fn ($i) => $this->shape($i), $v);
        }
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        $s = (string) $v;
        if (preg_match('/^-?\d+(?:\.\d+)?$/D', $s) && str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
            $s = ($s === '' || $s === '-' || $s === '-0') ? '0' : $s;
        }

        return $s;
    }

    private function emptyValue(mixed $v): bool
    {
        if (is_array($v)) {
            return !array_filter($v, fn ($x) => !$this->emptyValue($x));
        }

        return $v === null || $v === '';
    }

    private function same(mixed $a, mixed $b): bool
    {
        return json_encode($this->shape($a), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) === json_encode($this->shape($b), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /** Забывает прочитанные карточки: после записи строки они устаревают. */
    public function forget(): void
    {
        $this->items = [];
    }

    private function schema(string $entity): array
    {
        return $this->schemas[$entity] ??= (new Schema($this->db))->fields($entity);
    }

    /** Значения списка поля по алиасу или названию (не более двух: два — неоднозначность). Результат кешируется на время шага. */
    private function optionIds(int $field, string $alias, ?string $title = null): array
    {
        $key = $field . '|' . $alias . '|' . ($title ?? $alias);

        return $this->options[$key] ??= $this->db->setQuery('SELECT id FROM #__dharmashop_field_options WHERE field_id=' . $field . ' AND (alias=' . $this->db->quote($alias) . ' OR title=' . $this->db->quote($title ?? $alias) . ')', 0, 2)->loadColumn();
    }

    /** Категория, бренд или бейдж из ячейки: числовой ID существующей записи либо алиас/название; нет совпадения или несколько — ошибка строки. */
    private function taxonomyId(string $text, string $extension): int
    {
        $text = trim($text);
        $key = $extension . '|' . mb_strtolower($text);
        if (isset($this->taxonomy[$key])) {
            return $this->taxonomy[$key];
        }
        $id = 0;
        if (ctype_digit($text)) {
            $id = (int) $this->db->setQuery('SELECT id FROM #__categories WHERE id=' . (int) $text . ' AND extension=' . $this->db->quote($extension))->loadResult();
        }
        if (!$id && str_contains($text, '/')) {
            // Путь из алиасов (так его отдаёт экспорт): однозначно определяет запись в дереве.
            $ids = $this->db->setQuery('SELECT id FROM #__categories WHERE extension=' . $this->db->quote($extension) . ' AND published>=0 AND path=' . $this->db->quote(trim($text, '/')), 0, 2)->loadColumn();
            if (count($ids) === 1) {
                $id = (int) $ids[0];
            }
        }
        if (!$id) {
            $ids = $this->db->setQuery('SELECT id FROM #__categories WHERE extension=' . $this->db->quote($extension) . ' AND parent_id>0 AND published>=0 AND (alias=' . $this->db->quote(ApplicationHelper::stringURLSafe($text)) . ' OR title=' . $this->db->quote($text) . ')', 0, 2)->loadColumn();
            if (count($ids) !== 1) {
                throw new \RuntimeException('COM_DHARMASHOP_IMPORT_TAXONOMY');
            }
            $id = (int) $ids[0];
        }

        return $this->taxonomy[$key] = $id;
    }

    /** Публикация: 0/1, да/нет, yes/no, true/false, опубликован/снят (без учёта регистра). */
    private function stateValue(string $text): int
    {
        return match (mb_strtolower(trim($text))) {
            '1', 'да', 'yes', 'true', 'y', 'on', 'опубликован', 'опубликовано', 'включен', 'включено' => 1,
            '0', 'нет', 'no', 'false', 'n', 'off', 'снят', 'не опубликован', 'скрыт', 'выключен', 'выключено' => 0,
            default => throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE'),
        };
    }
    public function model(string $entity): object
    {
        return Factory::getApplication()->bootComponent('com_dharmashop')->getMVCFactory()->createModel($entity==='products'?'Product':'Category','Administrator',['ignore_request'=>true]);
    }
    public function item(string $entity, int $id): array
    {
        if(!$id){return [];}
        $memo=$entity.':'.$id;if(isset($this->items[$memo])){return $this->items[$memo];}
        $item=(array)$this->model($entity)->getItem($id);
        if(empty($item['id'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_NOT_FOUND');}
        if($entity!=='products'){
            if(($item['extension']??'')!==Schema::EXTENSIONS[$entity]){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CONFLICT');}
            $item['params']=(new Registry($item['params']??[]))->toArray();$item['state']=$item['published'];
            $item['cover']=$item['params']['image']??'';$item['introtext']=$item['params']['introtext']??'';
        }
        if(in_array($entity,['products','categories'],true)){$item['custom_fields']=(new FieldValues($this->db))->values($entity==='products'?'product':'category',$item);}
        return $this->items[$memo]=$item;
    }
    public function find(array $profile, string $key, array $patch = []): int
    {
        $entity=$profile['entity'];$mode=$profile['params']['key'];
        if($mode==='external_id'){return (int)$this->db->setQuery('SELECT id FROM #__dharmashop_external_ids WHERE entity='.$this->db->quote($entity).' AND source='.$this->db->quote($profile['params']['source_code']).' AND external_id='.$this->db->quote($key))->loadResult();}
        if($mode==='sku'){$sql='SELECT product_id FROM #__dharmashop_variants WHERE sku='.$this->db->quote($key);}
        else{
            $table=$entity==='products'?'#__dharmashop_products':'#__categories';
            $sql='SELECT id FROM '.$table.' WHERE '.($mode==='id'?'id='.(int)$key:'alias='.$this->db->quote($key));
            if($entity!=='products'){$sql.=' AND extension='.$this->db->quote(Schema::EXTENSIONS[$entity]);}
            elseif($mode==='alias'&&!empty($patch['catid'])){$sql.=' AND catid='.(int)$patch['catid'];}
        }
        $ids=$this->db->setQuery($sql,0,2)->loadColumn();if(count($ids)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CONFLICT');}return (int)($ids[0]??0);
    }
    private function list(mixed $value, array $settings): array
    {
        if(is_array($value)){return $value;}$text=trim((string)$value);
        if(str_starts_with($text,'[')){ $v=json_decode($text,true,32,JSON_THROW_ON_ERROR);if(!is_array($v)||!array_is_list($v)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return $v; }
        return $text===''?[]:array_map('trim',explode($settings['list_separator'],$text));
    }
    private function value(string $type, string $text, array $settings): mixed
    {
        if($text==='__CLEAR__'){return in_array($type,['ids','images','badges','extracats'],true)?[]:($type==='brand'?0:'');}
        if($text==='\\__CLEAR__'){return '__CLEAR__';}
        if($type==='id'){if(!ctype_digit(trim($text))){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return (int)$text;}
        if($type==='state'){return $this->stateValue($text);}
        if($type==='brand'){return $this->taxonomyId($text,'com_dharmashop.brand');}
        if($type==='badges'){return array_values(array_unique(array_map(fn($v)=>$this->taxonomyId((string)$v,'com_dharmashop.badge'),$this->list($text,$settings))));}
        if($type==='extracats'){return array_values(array_unique(array_map(fn($v)=>$this->taxonomyId((string)$v,'com_dharmashop'),$this->list($text,$settings))));}
        if($type==='inherit'){if(!in_array($text,['own','group','empty'],true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return $text;}
        if($type==='decimal'){ $number=str_replace(',','.',trim($text));if(!preg_match('/^-?\d{1,12}(?:\.\d{1,6})?$/D',$number)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return $number; }
        if($type==='ids'){return array_map(function($v){if(!ctype_digit((string)$v)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return (int)$v;},$this->list($text,$settings));}
        if($type==='date'){if(!preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?)?$/D',$text)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}$date=new \DateTimeImmutable($text,new \DateTimeZone('UTC'));$errors=\DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
        return $type==='html'?ComponentHelper::filterText($text):trim(strip_tags($text));
    }
    public function prepare(array $profile, array $raw, int $job): array
    {
        $p=$profile['params'];$schema=$this->schema($profile['entity']);$values=[];$seen=[];
        $pending=[];$deps=new Dependencies($this->db);
        foreach($p['mapping'] as $mapping){$target=(string)($mapping['target']??'');if($target===''){continue;}
            if($target==='__new_field'){$d=$deps->definition($mapping,$profile);$target='field.'.$d['alias'];if(!isset($schema[$target])){$pending[$target]=$d;$schema[$target]=['type'=>'pending'];}}
            if(!isset($schema[$target])||isset($seen[$target])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_MAPPING_ERROR');}$seen[$target]=true;
            $index=filter_var($mapping['column']??null,FILTER_VALIDATE_INT);if($index===false||!array_key_exists($index,$raw)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_MAPPING_ERROR');}
            $text=(string)$raw[$index];if($text==='' && $target!==$p['key']){continue;}$values[$target]=$text;
        }
        $key=trim((string)($values[$p['key']]??''));if($key===''||mb_strlen($key)>100||preg_match('/[\x00-\x1f]/',$key)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_KEY_ERROR');}
        if($p['key']==='id'&&(!ctype_digit($key)||(int)$key<1)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_KEY_ERROR');}
        if($p['key']==='id'){$key=(string)(int)$key;}
        $patch=[];$assets=[];
        $current='';
        try{
        foreach($values as $target=>$text){$current=(string)$target;if(in_array($target,['external_id','id','parent_external_id'],true)){continue;}$definition=$schema[$target];$type=$definition['type'];
            if($type==='pending'){$d=$pending[$target];$options=$d['type']==='list'?$this->list($text,$p):[];
                if($d['type']==='list'){if(!$options||$text==='__CLEAR__'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$options=array_values(array_unique(array_map('strval',$options)));foreach($options as $option){if(!is_scalar($option)||trim((string)$option)===''||mb_strlen((string)$option)>255||ApplicationHelper::stringURLSafe((string)$option)===''){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}}}
                else{$decoded=$this->createdFieldValue($d,0,$text,$p);$provider=TypeRegistry::current()->get($d['type']);$provider->encode((object)['settings'=>$provider->normalizeSettings($d['params']),'required'=>0],[],$decoded);}
                $patch['__new_fields'][$d['alias']]=['definition'=>$d,'value'=>$text,'options'=>$options];continue;}
            if($type==='images'){$patch[$target]=$this->media($text,'image',$profile,$job,$assets);continue;}
            if($type==='field'){
                $field=$definition['field'];$fieldType=$field['type'];$fieldKey='f_'.$field['id'];
                if($fieldType==='list'&&!empty($p['create_missing_options'])&&$text!=='__CLEAR__'){
                    $options=$this->list($text,$p);$missing=false;
                    foreach($options as $option){if(!is_scalar($option)||trim((string)$option)===''||mb_strlen((string)$option)>255){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$alias=ApplicationHelper::stringURLSafe((string)$option);$ids=$this->optionIds((int)$field['id'],(string)$option);if(count($ids)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}if(!$ids){if($alias===''){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$missing=true;}}
                    if($missing){$d=$deps->definition(['field_title'=>$field['title'],'field_alias'=>$field['alias'],'field_type'=>'list'],$profile,true);if(empty($d['params']['list_multiple']??$d['params']['multiple']??0)&&count($options)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$patch['__new_fields'][$d['alias']]=['definition'=>$d,'existing'=>true,'value'=>$text,'options'=>array_values(array_unique(array_map('strval',$options)))];continue;}
                }
                if(in_array($fieldType,['gallery','files'],true)){$value=$this->media($text,$fieldType==='files'?'file':'image',$profile,$job,$assets);}
                elseif($text==='__CLEAR__'){$value=match($fieldType){'range'=>['from'=>'','to'=>''],'list','color'=>[],default=>''};}
                elseif(in_array($fieldType,['list','color','checkbox'],true)){
                    $settings=json_decode($field['params'],true)?:[];
                    if($fieldType==='checkbox'&&($settings['checkbox_mode']??'single')==='single'){$value=in_array(mb_strtolower($text),['1','true','да','yes'],true)?1:(in_array(mb_strtolower($text),['0','false','нет','no','__CLEAR__'],true)?0:throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE'));}
                    else{$value=[];foreach($this->list($text,$p) as $alias){$ids=$this->optionIds((int)$field['id'],(string)$alias);if(count($ids)!==1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$value[]=(int)$ids[0];}if(in_array($fieldType,['list','color'],true)&&empty($settings['list_multiple']??$settings['multiple']??0)){if(count($value)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$value=$value[0]??'';}}
                }elseif($fieldType==='range'){$range=$this->list($text,$p);if(count($range)!==2){$range=preg_split('/\s*[–]|\.\.\s*/u',$text);}if(count($range)!==2){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}$value=['from'=>$this->value('decimal',(string)$range[0],$p),'to'=>$this->value('decimal',(string)$range[1],$p)];}
                else{$value=$this->value($fieldType==='number'?'decimal':($fieldType==='editor'?'html':'text'),$text,$p);}
                $patch['custom_fields'][$fieldKey]=$value;$patch['custom_fields_present'][$fieldKey]=1;continue;
            }
            $patch[$target]=$this->value($type,$text,$p);
        }
        }catch(\RuntimeException $e){
            // Ошибка значения называет колонку и значение: по одному коду «неверное значение» строку не исправить.
            if(!preg_match('/^COM_DHARMASHOP_IMPORT_(?:VALUE|OPTION|TAXONOMY|SIZE|PATH)$/D',$e->getMessage())){throw $e;}
            throw new \InvalidArgumentException(\Joomla\CMS\Language\Text::_($e->getMessage()).' — '.($schema[$current]['label']??$current).': «'.mb_substr(trim((string)($values[$current]??'')),0,80).'»');
        }
        if(isset($patch['category_path'])){$chain=$deps->categories($patch['category_path'],$profile);$last=end($chain);if($last['id']){$patch['catid']=$last['id'];}else{$patch['__category_chain']=$chain;}unset($patch['category_path']);}
        $id=$this->find($profile,$key,$patch);$before=$this->item($profile['entity'],$id);
        $this->settleMedia($patch,$before,$id,$profile,$job,$assets);
        $action=$id?'update':'create';if(($id&&!$p['update_existing'])||(!$id&&!$p['create_new'])){$action='skip';}
        if($action==='skip'){return ['key'=>$key,'target'=>$id,'action'=>'skip','patch'=>[],'before'=>[],'assets'=>[]];}
        if(!$id&&$p['key']==='id'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_NOT_FOUND');}
        if(!$id){
            if(empty($patch['title'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_TITLE_ERROR');}
            $patch['state']??=$p['new_state'];
            if(!isset($patch['alias'])){$patch['alias']=mb_substr(ApplicationHelper::stringURLSafe($patch['title']),0,170)?:'item';$patch['__alias_auto']=1;}
            if($profile['entity']==='products'){$patch['catid']??=(int)($p['default_category']??0);if($patch['catid']<2&&!isset($patch['__category_chain'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}}
            else{$patch['parent_id']??=1;}
        }
        if(isset($values['parent_external_id'])&&trim($values['parent_external_id'])!==''){
            if($p['key']!=='external_id'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_KEY_ERROR');}
            $patch['__parent_key']=trim($values['parent_external_id']);
        }
        if($profile['entity']==='badges'&&(!empty($patch['__parent_key'])||($patch['parent_id']??1)!==1)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}
        if($profile['entity']==='products'){
            if(isset($patch['cover'])&&isset($patch['gallery'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_MAPPING_ERROR');}
            if(isset($patch['cover'])){$patch['gallery']=$patch['cover'];unset($patch['cover']);}
        }
        // Подпись обложки хранится как подпись первой картинки галереи: без этого модель затирает cover_alt при записи галереи.
        if($profile['entity']==='products'&&isset($patch['cover_alt'],$patch['gallery'][0])&&is_array($patch['gallery'][0])&&trim((string)($patch['gallery'][0]['alt']??''))===''){$patch['gallery'][0]['alt']=mb_substr(trim((string)$patch['cover_alt']),0,255);}
        $this->validate($profile,array_replace($before,$patch,['id'=>$id]));
        $expected=[];if(isset($patch['__new_fields'])){$expected['custom_fields']=$before['custom_fields']??[];$expected['custom_fields_present']=null;}if(isset($patch['__category_chain'])){$expected['catid']=$before['catid']??null;}foreach($patch as $k=>$v){if(!str_starts_with($k,'__')){$expected[$k]=$before[$k]??null;}}
        if($id&&!$assets&&!isset($patch['__parent_key'])&&!isset($patch['__new_fields'])&&!isset($patch['__category_chain'])){
            $same=$this->isUnchanged($before,$patch);
            if($same){$action='unchanged';}
        }
        return ['key'=>$key,'target'=>$id,'action'=>$action,'patch'=>$patch,'before'=>$expected,'assets'=>array_values(array_unique($assets))];
    }
    public function createdFieldValue(array $definition, int $id, string $text, array $settings): mixed
    {
        $type=$definition['type'];
        if($text==='__CLEAR__'){return $type==='list'?[]:($type==='range'?['from'=>'','to'=>'']:'');}
        if($type==='list'){
            $result=[];foreach($this->list($text,$settings) as $value){$alias=ApplicationHelper::stringURLSafe((string)$value);$ids=$this->optionIds($id,$alias,(string)$value);if(count($ids)!==1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}$result[]=(int)$ids[0];}if(empty($definition['params']['list_multiple']??$definition['params']['multiple']??0)){if(count($result)>1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_OPTION');}return $result[0]??'';}return $result;
        }
        if($type==='checkbox'){return match(mb_strtolower(trim($text))){'1','true','да','yes'=>1,'0','false','нет','no'=>0,default=>throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE')};}
        if($type==='range'){$range=$this->list($text,$settings);if(count($range)!==2){$range=preg_split('/\s*[–]|\.\.\s*/u',$text);}if(count($range)!==2){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}return ['from'=>$this->value('decimal',(string)$range[0],$settings),'to'=>$this->value('decimal',(string)$range[1],$settings)];}
        return $this->value($type==='number'?'decimal':($type==='editor'?'html':'text'),$text,$settings);
    }
    private function media(string $text, string $kind, array $profile, int $job, array &$assets): array
    {
        $p=$profile['params'];$result=[];$list=$text==='__CLEAR__'?[]:$this->list($text,$p);
        if(count($list)>100){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SIZE');}
        foreach($list as $entry){$entry=is_array($entry)?$entry:['url'=>(string)$entry];$url=(string)($entry['url']??$entry['path']??'');if($url===''){continue;}
            // Own absolute URLs are local references; no loopback HTTP request is made.
            $origin=rtrim(\Joomla\CMS\Uri\Uri::root(),'/');
            if(str_starts_with($url,$origin.'/images/')){$url=rawurldecode(substr($url,strlen($origin)+1));}
            elseif(!preg_match('~^https?://~i',$url)&&!str_starts_with(ltrim($url,'/'),'images/')&&!empty($p['media_base_url'])){$url=rtrim($p['media_base_url'],'/').'/'.ltrim($url,'/');}
            if(preg_match('~^https?://~i',$url)){
                if(empty($p[$kind==='image'?'download_images':'download_files'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_MEDIA_DISABLED');}
                Remote::validateUrl($url,$p['media_hosts'],!empty($p['allow_http']));
                // Файл ставится в очередь скачивания позже (settleMedia): если эта ссылка уже скачана для этой записи, повторно файл не качается.
                $hash=hash('sha256',$kind.'|'.$url);if(in_array($hash,array_column($result,'hash'),true)){continue;} // одна и та же ссылка в списке дважды — один файл
                $result[]=['__download'=>true,'url'=>$url,'kind'=>$kind,'hash'=>$hash,'alt'=>mb_substr(strip_tags((string)($entry['alt']??'')),0,255),'title'=>mb_substr(strip_tags((string)($entry['title']??'')),0,255)];
            }else{
                $local=FileStorage::path(ltrim($url,'/'));$root=realpath(JPATH_ROOT.'/images');$path=$local!==''?realpath(JPATH_ROOT.'/'.$local):false;
                if(!$root||!$path||!str_starts_with($path,$root.'/')||!is_file($path)||is_link(JPATH_ROOT.'/'.$local)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}
                if($kind==='image'){(new ImageUploader(ComponentHelper::getParams('com_media'),JPATH_ROOT,ComponentHelper::getParams('com_dharmashop')))->inspectPrepared($path,basename($local));}
                $result[]=['path'=>$local,'alt'=>mb_substr(strip_tags((string)($entry['alt']??'')),0,255),'title'=>mb_substr(strip_tags((string)($entry['title']??'')),0,255)];
            }
        }return $result;
    }
    /**
     * Ссылки на файлы из ячеек становятся элементами плана: если эта ссылка уже скачана для этой записи и файл по-прежнему в её карточке, берётся он
     * (повторный импорт ничего не меняет и не качает заново), иначе файл ставится в очередь скачивания.
     */
    private function settleMedia(array &$patch, array $before, int $target, array $profile, int $job, array &$assets): void
    {
        $paths=[];$collect=function(mixed $node)use(&$collect,&$paths):void{if(!is_array($node)){return;}if(isset($node['path'])&&is_string($node['path'])){$paths[$node['path']]=true;}foreach($node as $v){if(is_array($v)){$collect($v);}}};
        $collect($before);
        $settle=function(mixed $node)use(&$settle,$paths,$target,$profile,$job,&$assets):mixed{
            if(!is_array($node)){return $node;}
            if(!empty($node['__download'])){
                $meta=array_intersect_key($node,array_flip(['alt','title']));
                $known=$target?(string)$this->db->setQuery('SELECT path FROM #__dharmashop_import_media WHERE source='.$this->db->quote($profile['params']['source_code']).' AND entity='.$this->db->quote($profile['entity']).' AND target_id='.$target.' AND url_hash='.$this->db->quote($node['hash']))->loadResult():'';
                if($known!==''&&isset($paths[$known])){return ['path'=>$known]+$meta;}
                $id=$this->queueDownload($node['url'],$node['kind'],$node['hash'],$profile,$job);$assets[]=$id;
                return ['asset'=>$id]+$meta;
            }
            foreach($node as $k=>$v){if(is_array($v)){$node[$k]=$settle($v);}}
            return $node;
        };
        $patch=$settle($patch);
    }
    /** Ставит скачивание файла в очередь запуска (один файл на ссылку в пределах запуска; лимит числа файлов из профиля или общих настроек). */
    private function queueDownload(string $url, string $kind, string $hash, array $profile, int $job): int
    {
        $id=(int)$this->db->setQuery('SELECT id FROM #__dharmashop_import_files WHERE job_id='.$job.' AND url_hash='.$this->db->quote($hash))->loadResult();
        if($id){return $id;}
        [$max]=Profile::mediaLimits($profile['params']);
        if((int)$this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_import_files WHERE job_id='.$job)->loadResult()>=$max){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_QUOTA');}
        $file=(object)['job_id'=>$job,'created_by'=>(int)Factory::getApplication()->getIdentity()->id,'kind'=>$kind,'state'=>'pending','name'=>'download','url'=>$url,'url_hash'=>$hash,'created'=>gmdate('Y-m-d H:i:s')];
        $this->db->insertObject('#__dharmashop_import_files',$file,'id');
        return (int)$file->id;
    }
    /** После записи карточки: запоминает, какой ссылке соответствует каждый сохранённый файл записи (для повторного импорта без повторного скачивания). */
    public function rememberMedia(array $profile, int $target, array $plan): void
    {
        if($target<1){return;}
        $walk=function(mixed $node)use(&$walk,$profile,$target):void{
            if(!is_array($node)){return;}
            if(isset($node['_src'],$node['path'])&&is_string($node['_src'])&&is_string($node['path'])&&$node['path']!==''){
                $this->db->setQuery('REPLACE INTO #__dharmashop_import_media (source,entity,target_id,url_hash,path,created) VALUES ('.$this->db->quote($profile['params']['source_code']).','.$this->db->quote($profile['entity']).','.$target.','.$this->db->quote($node['_src']).','.$this->db->quote(mb_substr($node['path'],0,500)).','.$this->db->quote(gmdate('Y-m-d H:i:s')).')')->execute();
            }
            foreach($node as $v){if(is_array($v)){$walk($v);}}
        };
        $walk($plan['patch']??[]);
    }
    /** Все значения плана совпадают с текущей карточкой (по смыслу): записывать нечего. Сравниваются только ключи, заявленные в плане. */
    public function isUnchanged(array $before, array $patch): bool
    {
        $same=true;
        foreach($patch as $k=>$v){
            if(str_starts_with($k,'__')||$k==='custom_fields_present'){continue;}
            $current=$before[$k]??null;
            // Значения полей: сравниваются только поля, которые есть в файле; остальные значения карточки не затрагиваются.
            if($k==='custom_fields'){
                $current=array_intersect_key((array)$current,(array)$v);
                // Очистка поля, в котором и так пусто, ничего не меняет.
                $v=array_filter((array)$v,fn($val,$name)=>isset($current[$name])||!$this->emptyValue($val),ARRAY_FILTER_USE_BOTH);
            }
            if(!$this->same($v,$current)){$same=false;break;}
        }
        return $same;
    }
    /** Убирает из плана файлы, которые не удалось скачать (вместе с их элементами в галереях и полях). Возвращает true, если что-то убрано. */
    public function withoutFailedAssets(array &$plan): bool
    {
        $assets=array_values(array_filter(array_map('intval',(array)($plan['assets']??[]))));if(!$assets){return false;}
        $failed=array_map('intval',$this->db->setQuery("SELECT id FROM #__dharmashop_import_files WHERE state='failed' AND id IN (".implode(',',$assets).')')->loadColumn());
        if(!$failed){return false;}
        $strip=function(mixed $node) use (&$strip,$failed):mixed{
            if(!is_array($node)){return $node;}
            $out=[];foreach($node as $key=>$value){
                if(is_array($value)&&isset($value['asset'])&&in_array((int)$value['asset'],$failed,true)){continue;}
                $out[$key]=$strip($value);
            }
            return array_is_list($node)?array_values($out):$out;
        };
        $plan['patch']=$strip($plan['patch']??[]);$plan['assets']=array_values(array_diff($assets,$failed));
        return true;
    }
    public function validateAssets(array $plan): void
    {
        foreach($plan['assets'] as $id){$file=$this->db->setQuery('SELECT state,token,folder,name,kind FROM #__dharmashop_import_files WHERE id='.(int)$id)->loadAssoc();if(($file['state']??'')!=='ready'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_MEDIA_FAILED');}
            if($file['kind']==='image'){$path=(new Storage($file['folder']))->temporary($file['token']);try{(new ImageUploader(ComponentHelper::getParams('com_media'),JPATH_ROOT,ComponentHelper::getParams('com_dharmashop')))->inspectPrepared($path,self::imageName($file['name'],$path));}finally{unlink($path);}}
        }
        foreach($plan['patch']['custom_fields']??[] as $name=>$items){
            $field=$this->db->setQuery('SELECT id,type,params FROM #__dharmashop_fields WHERE id='.(int)substr($name,2))->loadAssoc();
            if(!$field||$field['type']!=='files'){continue;}
            $settings=FileStorage::settings(json_decode($field['params'],true)?:[]);
            if(count($items)>$settings['files_max_count']){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SIZE');}
            $storage=new FileStorage(JPATH_ROOT,(int)$field['id'],$settings);
            foreach($items as $item){
                if(empty($item['asset'])){$storage->inspect($item['path']);continue;}
                $file=$this->db->setQuery('SELECT token,folder,name FROM #__dharmashop_import_files WHERE id='.(int)$item['asset'])->loadAssoc();$path=(new Storage($file['folder']))->temporary($file['token']);
                try{$storage->inspectPrepared($path,$file['name']);}finally{unlink($path);}
            }
        }
    }
    /** Имя изображения с расширением по реальному содержимому: сайты отдают, например, JPEG под именем .png, а загрузчик сверяет тип и расширение. */
    private static function imageName(string $name, string $path): string
    {
        $mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif','image/svg+xml'=>'svg'][$mime]??'';
        if($ext===''){return $name;}
        $current=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if($current===$ext||($ext==='jpg'&&$current==='jpeg')){return $name;}
        return ($current!==''?pathinfo($name,PATHINFO_FILENAME):$name).'.'.$ext;
    }
    /**
     * Алиас, образованный из названия, делается уникальным в своей категории (товар) или у родителя (категория): сначала обычный слаг, затем -2, -3…,
     * и только после десяти конфликтов — слаг с хвостом из ключа. Итог сохраняется в плане до записи, поэтому повтор после обрыва находит ту же запись.
     */
    public function uniqueAlias(array $profile, array $plan): array
    {
        if (empty($plan['patch']['__alias_auto']) || $plan['target']) {
            unset($plan['patch']['__alias_auto']);

            return $plan;
        }
        $base = (string) $plan['patch']['alias'];
        $entity = $profile['entity'];
        $scope = $entity === 'products'
            ? ' AND catid=' . (int) ($plan['patch']['catid'] ?? 0)
            : ' AND parent_id=' . (int) ($plan['patch']['parent_id'] ?? 1) . ' AND extension=' . $this->db->quote(Schema::EXTENSIONS[$entity]);
        $table = $entity === 'products' ? '#__dharmashop_products' : '#__categories';
        $candidate = $base;
        for ($n = 2; $n <= 11; $n++) {
            if (!(int) $this->db->setQuery('SELECT COUNT(*) FROM ' . $table . ' WHERE alias=' . $this->db->quote($candidate) . $scope)->loadResult()) {
                break;
            }
            $candidate = mb_substr($base, 0, 160) . '-' . $n;
            if ($n === 11) {
                $candidate = mb_substr($base, 0, 160) . '-' . substr(hash('sha256', $profile['params']['source_code'] . '|' . $plan['key']), 0, 12);
            }
        }
        $plan['patch']['alias'] = $candidate;
        unset($plan['patch']['__alias_auto']);

        return $plan;
    }

    public function promote(array $profile, array $plan): array
    {
        $walk=function(array $items, ?array $field=null)use($profile,$plan):array{
            foreach($items as &$item){if(!isset($item['asset'])){continue;}$file=$this->db->setQuery('SELECT token,folder,name,kind,url_hash FROM #__dharmashop_import_files WHERE id='.(int)$item['asset'])->loadAssoc();$temp=(new Storage($file['folder']))->temporary($file['token']);
                try{
                    if($field&&$field['type']==='files'){$settings=FileStorage::settings(json_decode($field['params'],true)?:[]);$data=(new FileStorage(JPATH_ROOT,(int)$field['id'],$settings))->storePrepared($temp,$file['name'],['target'=>$profile['entity']==='products'?'product':'category','id'=>$plan['target'],'alias'=>$plan['patch']['alias']??'import','field_alias'=>$field['alias']]);}
                    else{$u=new ImageUploader(ComponentHelper::getParams('com_media'),JPATH_ROOT,ComponentHelper::getParams('com_dharmashop'));$name=self::imageName($file['name'],$temp);$folder=$profile['entity']==='products'?'products':'categories';$scope=null;
                        // Галерея пользовательского поля хранится в личной папке поля, как при ручной загрузке: field-galleries/<ID поля>/<папка из настроек поля>.
                        if($field&&$field['type']==='gallery'){$settings=json_decode($field['params'],true)?:[];$gallery=TypeRegistry::current()->get('gallery')->normalizeSettings($settings);$folder='field-galleries';$scope=(int)$field['id'].'/'.$gallery['gallery_folder'];}
                        $data=$u->store(['tmp_name'=>$temp,'name'=>$name,'size'=>filesize($temp),'error'=>UPLOAD_ERR_OK],$folder,$scope,false);}
                    $item=$data+array_intersect_key($item,array_flip(['alt','title']))+['_src'=>(string)$file['url_hash']];
                }finally{unlink($temp);}
            }unset($item);return $items;
        };
        foreach(['gallery','cover'] as $name){if(isset($plan['patch'][$name])){$plan['patch'][$name]=$walk($plan['patch'][$name]);}}
        foreach($plan['patch']['custom_fields']??[] as $name=>$items){$id=(int)substr($name,2);$f=$this->db->setQuery('SELECT id,alias,type,params FROM #__dharmashop_fields WHERE id='.$id)->loadAssoc();if($f&&in_array($f['type'],['files','gallery'],true)){$plan['patch']['custom_fields'][$name]=$walk($items,$f);}}
        return $plan;
    }
    public function assertAccess(array $profile, int $id, array $patch): void
    {
        $user=Factory::getApplication()->getIdentity();$action=$id?'core.edit':'core.create';$resource=$profile['entity']==='products'?($id?'com_dharmashop.product.'.$id:(isset($patch['__category_chain'])?'com_dharmashop':'com_categories.category.'.(int)($patch['catid']??0))):($id?'com_categories.category.'.$id:Schema::EXTENSIONS[$profile['entity']]);
        if(!$user->authorise($action,$resource)){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
        if(isset($patch['state'])&&!$user->authorise('core.edit.state',$resource)){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
        if($id){$item=$this->item($profile['entity'],$id);if(!empty($item['checked_out'])&&(int)$item['checked_out']!==(int)$user->id){throw new \RuntimeException('JLIB_APPLICATION_ERROR_CHECKOUT_USER_MISMATCH');}}
    }
    /** Read-only catalogue checks before any native model writes assets or relations. */
    private function validate(array $profile, array $data): void
    {
        foreach(['title'=>255,'alias'=>255,'sku'=>100,'metadesc'=>1024,'metakey'=>1024] as $key=>$max){
            if(isset($data[$key])&&mb_strlen((string)$data[$key])>$max){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}
        }
        $references=$profile['entity']==='products'
            ? ['catid'=>'com_dharmashop','brand_id'=>'com_dharmashop.brand','extra_catids'=>'com_dharmashop','badge_ids'=>'com_dharmashop.badge']
            : ['parent_id'=>Schema::EXTENSIONS[$profile['entity']]];
        foreach($references as $name=>$extension){
            foreach((array)($data[$name]??[]) as $ref){
                if((int)$ref<1){continue;}if($name==='parent_id'&&(int)$ref===1){continue;}
                $valid=$this->db->setQuery('SELECT id FROM #__categories WHERE id='.(int)$ref.' AND extension='.$this->db->quote($extension))->loadResult();
                if(!$valid||($name==='parent_id'&&(int)$ref===(int)($data['id']??0))){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}
            }
        }
        if($profile['entity']==='products'){
            foreach(['price','stock_qty','weight','length','width','height'] as $key){if(isset($data[$key])&&(float)$data[$key]<0){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_VALUE');}}
            \Dharma\Component\DharmaShop\Administrator\Pricing\Discount::assertValid(
                \Dharma\Component\DharmaShop\Administrator\Pricing\Discount::parse($data['discount']??null),isset($data['price'])?(float)$data['price']:null);
            $services=Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
            $services->variants()->assertSingle((int)($data['id']??0));
            $services->variants()->assertSkuAvailable((int)($data['id']??0),trim((string)($data['sku']??''))?:null);
        }
        if(!empty($data['custom_fields'])){$ids=(new FieldValues($this->db))->applicableIds($profile['entity']==='products'?'product':'category',$data);foreach(array_keys($data['custom_fields']) as $key){if(!in_array((int)substr($key,2),$ids,true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FIELD_SCOPE');}}}
        // Providers receive scalar values now; media placeholders are checked after download.
        $check=$data;$expected=[];
        foreach($check['custom_fields']??[] as $key=>$value){
            $field=$this->db->setQuery('SELECT type FROM #__dharmashop_fields WHERE id='.(int)substr($key,2))->loadResult();
            if(in_array($field,['files','gallery'],true)){unset($check['custom_fields'][$key],$check['custom_fields_present'][$key]);}
            else{$expected[]=(int)substr($key,2);}
        }
        if($expected){
            $prepared=(new FieldValues($this->db))->prepare($profile['entity']==='products'?'product':'category',$check);
            if(array_diff($expected,array_keys($prepared))){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FIELD_SCOPE');}
        }
    }
    public function apply(array $profile, array $plan): int
    {
        // Служебная метка источника файла нужна только для памяти о скачанном (rememberMedia) и в карточку не попадает.
        $strip=function(mixed $node)use(&$strip):mixed{if(!is_array($node)){return $node;}unset($node['_src']);foreach($node as $k=>$v){if(is_array($v)){$node[$k]=$strip($v);}}return $node;};
        $plan['patch']=$strip($plan['patch']??[]);
        $lock=$profile['entity']==='products'?'dharma.product.'.(int)$plan['target']:'dharma.categories';
        if(!(int)$this->db->setQuery('SELECT GET_LOCK('.$this->db->quote($lock).',10)')->loadResult()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
        try{return $this->applyLocked($profile,$plan);}
        finally{$this->db->setQuery('DO RELEASE_LOCK('.$this->db->quote($lock).')')->execute();}
    }
    private function applyLocked(array $profile, array $plan): int
    {
        $this->items=[];$id=(int)$plan['target'];$patch=$plan['patch'];$this->assertAccess($profile,$id,$patch);$model=$this->model($profile['entity']);
        $this->validate($profile,array_replace($this->item($profile['entity'],$id),$patch,['id'=>$id]));
        if($profile['entity']==='products'){
            if($id){$ok=$model->savePatch($id,$patch,$plan['before']);}
            else{$ok=$model->save($patch);}
        }else{
            $current=$this->item($profile['entity'],$id);
            foreach($plan['before'] as $key=>$value){if($id&&!$this->same($current[$key]??null,$value)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_STALE');}}
            $params=(array)($current['params']??[]);
            foreach(['cover'=>'image','introtext'=>'introtext'] as $from=>$to){if(isset($patch[$from])){$params[$to]=$from==='cover'?($patch[$from][0]['path']??''):$patch[$from];unset($patch[$from]);}}
            if(isset($patch['state'])){$patch['published']=$patch['state'];unset($patch['state']);}
            $data=array_replace(array_intersect_key($current,array_flip(['title','alias','parent_id','published','description','metadesc','metakey','access'])), $patch, ['id'=>$id,'params'=>$params,'extension'=>Schema::EXTENSIONS[$profile['entity']],'language'=>'*']);
            $ok=$model->save($data);
        }
        if(!$ok){throw new \InvalidArgumentException((string)$model->getError()?:'COM_DHARMASHOP_IMPORT_FAILURE');}
        return (int)$model->getState($profile['entity']==='products'?'product.id':'category.id');
    }
    public function recover(array $profile, array $plan): int
    {
        $patch=$plan['patch'];$table=$profile['entity']==='products'?'#__dharmashop_products':'#__categories';
        $sql='SELECT id FROM '.$table.' WHERE alias='.$this->db->quote($patch['alias']??'');
        $sql.=$profile['entity']==='products'?' AND catid='.(int)($patch['catid']??0):' AND parent_id='.(int)($patch['parent_id']??1).' AND extension='.$this->db->quote(Schema::EXTENSIONS[$profile['entity']]);
        $ids=$this->db->setQuery($sql,0,2)->loadColumn();if(!$ids){return 0;}if(count($ids)!==1){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_RECOVERY');}$id=(int)$ids[0];
        if(!$this->matches($profile,$id,$patch)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_RECOVERY');}
        return $id;
    }
    public function matches(array $profile, int $id, array $expected): bool
    {
        $item=$this->item($profile['entity'],$id);
        foreach($expected as $key=>$value){
            if($key==='custom_fields_present'){continue;}
            $current=$item[$key]??null;
            if($key==='cover'&&is_array($value)){$value=$value[0]['path']??'';}
            if($key==='custom_fields'){$current=array_intersect_key((array)$current,(array)$value);}
            if($key==='gallery'){
                $normal=static fn($list)=>array_map(static fn($i)=>['path'=>(string)($i['path']??''),'alt'=>(string)($i['alt']??''),'title'=>(string)($i['title']??'')],(array)$list);
                $current=$normal($current);$value=$normal($value);
            }
            if(in_array($key,['price','stock_qty','weight','length','width','height'],true)&&is_numeric($current)&&is_numeric($value)){
                $decimal=static function($n):string{$n=is_float($n)?sprintf('%.6F',$n):(string)$n;$parts=explode('.',$n,2);$integer=ltrim($parts[0],'0')?:'0';$fraction=rtrim($parts[1]??'','0');return $integer.($fraction!==''?'.'.$fraction:'');};
                if($decimal($current)===$decimal($value)){continue;}
            }
            if(!$this->same($current,$value)){return false;}
        }
        return true;
    }
}
