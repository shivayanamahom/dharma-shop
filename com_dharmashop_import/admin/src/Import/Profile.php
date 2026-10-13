<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;

final class Profile
{
    public static function prepare(array $data, DatabaseInterface $db): array
    {
        $data=array_intersect_key($data,array_flip(['id','title','state','entity','format','params']));
        $data['title']=trim(strip_tags((string)($data['title']??'')));$data['id']=(int)($data['id']??0);$data['state']=(int)($data['state']??1);
        $data['entity']=(string)($data['entity']??'categories');$data['format']=(string)($data['format']??'csv');
        $p=(array)($data['params']??[]);
        if($data['title']===''||!in_array($data['format'],['csv','xlsx'],true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_INVALID');}
        (new Schema($db))->fields($data['entity']);
        $p['source_code']=trim((string)($p['source_code']??''));
        if(!preg_match('/^[a-zA-Z0-9_-]{1,50}$/D',$p['source_code'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SOURCE_CODE_ERROR');}
        $p['source_type']=in_array($p['source_type']??'upload',['upload','url','path'],true)?($p['source_type']??'upload'):'upload';
        $p['key']=in_array($p['key']??'external_id',['external_id','id','sku','alias'],true)?($p['key']??'external_id'):'external_id';
        if($p['key']==='sku'&&$data['entity']!=='products'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_KEY_ERROR');}
        $p['separator']=in_array($p['separator']??'auto',['auto',';',',','tab'],true)?($p['separator']??'auto'):'auto';
        $p['encoding']=in_array($p['encoding']??'UTF-8',['UTF-8','Windows-1251'],true)?($p['encoding']??'UTF-8'):'UTF-8';
        $p['header']=(int)($p['header']??1);$p['skip_rows']=max(0,min(1000,(int)($p['skip_rows']??0)));$p['xlsx_sheet']=trim((string)($p['xlsx_sheet']??''));$p['new_state']=(int)($p['new_state']??0)===1?1:0;
        foreach(['create_new','update_existing','skip_errors','download_images','download_files','allow_http','skip_media_errors','create_missing_fields','create_missing_categories','create_missing_options'] as $key){$p[$key]=(int)(bool)($p[$key]??in_array($key,['create_new','update_existing','download_images','download_files'],true));}
        $p['mapping']=array_values(array_filter((array)($p['mapping']??[]),static fn($m)=>is_array($m)&&isset($m['column'])));$p['list_separator']=(string)($p['list_separator']??' | ');
        if($p['list_separator']===''||strlen($p['list_separator'])>20){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_INVALID');}
        $hosts=$p['media_hosts']??[];
        $p['media_hosts']=array_values(array_unique(array_filter(array_map('trim',is_array($hosts)?$hosts:(preg_split('/[\s,;]+/',(string)$hosts)?:[])))));
        $p['category_separator']=(string)($p['category_separator']??'/');
        if($p['category_separator']===''||strlen($p['category_separator'])>10){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_INVALID');}
        $p['media_max_files']=max(0,min(200000,(int)($p['media_max_files']??0)));$p['media_max_mb']=max(0,min(102400,(int)($p['media_max_mb']??0)));
        $p['source_id']=(int)($p['source_id']??0);$data['params']=$p;return $data;
    }
    /** Лимиты скачивания медиа за запуск: значения профиля, а где 0 — общие настройки обмена (файлов, байт). */
    public static function mediaLimits(array $params): array
    {
        $config=\Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop');
        $files=(int)($params['media_max_files']??0);$mb=(int)($params['media_max_mb']??0);
        if($files<=0){$files=max(1,min(50000,(int)$config->get('import_media_max_files',5000)));}
        if($mb<=0){$mb=max(16,min(10240,(int)$config->get('import_media_max_mb',512)));}
        return [$files,$mb*1048576];
    }
}

