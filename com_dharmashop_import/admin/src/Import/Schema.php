<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;

final class Schema
{
    public const ENTITIES = ['categories','brands','badges','products'];
    public const EXTENSIONS = ['categories'=>'com_dharmashop','brands'=>'com_dharmashop.brand','badges'=>'com_dharmashop.badge'];
    public function __construct(private DatabaseInterface $db) {}
    public function fields(string $entity): array
    {
        if(!in_array($entity,self::ENTITIES,true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_INVALID');}
        $fields=['external_id'=>'text','id'=>'id','title'=>'text','alias'=>'text','state'=>'state','introtext'=>'html','metadesc'=>'text','metakey'=>'text'];
        if($entity==='products'){
            $fields+=['sku'=>'text','catid'=>'id','category_path'=>'text','extra_catids'=>'extracats','brand_id'=>'brand','badge_ids'=>'badges','fulltext'=>'html','price'=>'decimal','discount'=>'text','discount_start'=>'date','discount_end'=>'date','stock_qty'=>'decimal','unit'=>'text','weight'=>'decimal','weight_unit'=>'text','length'=>'decimal','width'=>'decimal','height'=>'decimal','dimension_unit'=>'text','gallery'=>'images','cover'=>'images','cover_alt'=>'text','inherit_intro'=>'inherit','inherit_full'=>'inherit','inherit_images'=>'inherit'];
        }else{
            $fields+=['parent_id'=>'id','parent_external_id'=>'text','description'=>'html','cover'=>'images'];
        }
        $result=[];foreach($fields as $name=>$type){$result[$name]=['label'=>Text::_('COM_DHARMASHOP_IMPORT_COL_'.strtoupper($name)),'type'=>$type];}
        $target=$entity==='products'?'product':'category';
        if(in_array($entity,['products','categories'],true)){
            foreach($this->db->setQuery('SELECT id,alias,title,type,params,required FROM #__dharmashop_fields WHERE state=1 AND level=1 AND target='.$this->db->quote($target).' ORDER BY group_id,ordering,id')->loadAssocList() as $field){
                if(!in_array($field['type'],['text','editor','number','range','list','checkbox','color','gallery','files'],true)){continue;}
                $result['field.'.$field['alias']]=['label'=>$field['title'],'type'=>'field','field'=>$field];
            }
        }
        return $result;
    }
    /** Колонки нашего экспорта → назначения импорта (ключ колонки или её подпись). */
    private const EXPORT = ['title'=>'title','alias'=>'alias','sku'=>'sku','state'=>'state','category_path'=>'category_path','brand'=>'brand_id','badges'=>'badge_ids',
        'category_paths_extra'=>'extra_catids','introtext'=>'introtext','fulltext'=>'fulltext','base_price'=>'price','discount'=>'discount','discount_start'=>'discount_start',
        'discount_end'=>'discount_end','stock_qty'=>'stock_qty','unit'=>'unit','weight'=>'weight','weight_unit'=>'weight_unit','length'=>'length','width'=>'width','height'=>'height',
        'dimension_unit'=>'dimension_unit','cover_alt'=>'cover_alt','gallery'=>'gallery','gallery_json'=>'gallery','metadesc'=>'metadesc','metakey'=>'metakey',
        'inherit_intro'=>'inherit_intro','inherit_full'=>'inherit_full','inherit_images'=>'inherit_images','description'=>'description','cover'=>'cover'];

    public static function automatic(string $header, array $schema): string
    {
        $normal=static fn($s)=>mb_strtolower(trim((string)$s));$needle=$normal($header);
        // Колонки с подписями медиа (…@json или «… (JSON)») возвращаются в то же поле без потери alt/title.
        $base=null;if(str_ends_with($needle,'@json')){$base=substr($needle,0,-5);}elseif(str_ends_with($needle,' (json)')){$base=substr($needle,0,-7);}
        if($base!==null){foreach($schema as $key=>$definition){if($normal($key)===$base||$normal($definition['label'])===$base){return $key;}}}
        foreach(self::EXPORT as $key=>$target){
            if($needle===$key||$needle===$normal(Text::_('COM_DHARMASHOP_EXPORT_COL_'.strtoupper($key)))){
                if($key==='cover'&&isset($schema['gallery'])){return '';}
                return isset($schema[$target])?$target:'';
            }
        }
        // Exported final price/available stock are deliberately not mapped to source quantities.
        $aliases=['base_price'=>'price','базовая цена'=>'price','цена без скидки (базовая)'=>'price','stock_qty'=>'stock_qty'];
        if(isset($aliases[$needle])&&isset($schema[$aliases[$needle]])){return $aliases[$needle];}
        if(in_array($needle,['price','stock','price_final','цена','остаток','цена со скидкой (текущая)'],true)){return '';}
        if(isset($schema[$header])){return $header;}
        $matches=[];foreach($schema as $key=>$definition){if($normal($definition['label'])===$needle){$matches[]=$key;}}
        return count($matches)===1?$matches[0]:'';
    }
}
