<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopPromotion\Coupon\Type;
use Dharma\Component\DharmaShop\Administrator\Promotion\Context;
use Dharma\Component\DharmaShop\Administrator\Promotion\PromotionTypeInterface;
use Dharma\Component\DharmaShop\Administrator\Promotion\PromotionService;
use Dharma\Component\DharmaShop\Administrator\Promotion\Result;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
final class CouponType implements PromotionTypeInterface
{
    public function id(): string { return 'coupon'; }
    public function label(): string { return 'PLG_DHARMASHOPPROMOTION_COUPON'; }
    public function description(): string { return 'PLG_DHARMASHOPPROMOTION_COUPON_DESC'; }
    public function settingsPath(): string { return dirname(__DIR__,2) . '/forms/settings.xml'; }
    public function requiresCode(): bool { return true; }

    /** Промокоды из отдельной таблицы кодов для общей колонки списка акций. */
    public function listSummary(array $settings, string $codes = ''): string
    {
        return trim($codes) !== '' ? trim($codes) : '—';
    }

    public function normalizeSettings(array $settings): array
    {
        $rows = $settings['coupons'] ?? [];
        if (!is_array($rows) || !$rows) throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
        $coupons=[]; $seen=[]; $allCategoryIds=[];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $code=PromotionService::normalizeCode((string)($row['code']??''));
            $mode=(string)($row['discount_type']??'percent');
            $value=filter_var(str_replace(',','.',(string)($row['discount_value']??'')),FILTER_VALIDATE_FLOAT);
            $minimum=filter_var(str_replace(',','.',(string)($row['minimum_subtotal']??'0')),FILTER_VALIDATE_FLOAT);
            $maximum=filter_var(str_replace(',','.',(string)($row['maximum_discount']??'0')),FILTER_VALIDATE_FLOAT);
            $starts=self::normalizeDate($row['starts']??null,false);
            $ends=self::normalizeDate($row['ends']??null,true);
            if ($code==='' || isset($seen[$code]) || !in_array($mode,['percent','amount'],true) || $value===false || $value<=0 || ($mode==='percent' && $value>100) || $minimum===false || $minimum<0 || $maximum===false || $maximum<0 || ($starts!==null && $ends!==null && $starts>$ends)) throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
            $rawCategoryIds=$row['category_ids']??[];
            if (is_string($rawCategoryIds)) $rawCategoryIds=trim($rawCategoryIds)===''?[]:explode(',',$rawCategoryIds);
            if (is_int($rawCategoryIds)) $rawCategoryIds=[$rawCategoryIds];
            if (!is_array($rawCategoryIds)) throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
            $categoryIds=[];
            foreach($rawCategoryIds as $rawCategoryId){
                if($rawCategoryId===''||$rawCategoryId===null)continue;
                $categoryId=filter_var($rawCategoryId,FILTER_VALIDATE_INT);
                if($categoryId===false||$categoryId<1)throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
                $categoryIds[]=(int)$categoryId;
            }
            $categoryIds=array_values(array_unique($categoryIds));
            if (count($categoryIds)>100) throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
            foreach($categoryIds as $categoryId)$allCategoryIds[$categoryId]=true;
            $seen[$code]=true;
            $coupons[]=['code'=>$code,'ordering'=>(int)($row['ordering']??count($coupons)),'params'=>[
                'description'=>mb_substr(trim(strip_tags((string)($row['description']??''))),0,500),
                'discount_type'=>$mode,'discount_value'=>round((float)$value,2),
                'minimum_subtotal'=>round((float)$minimum,2),'maximum_discount'=>round((float)$maximum,2),
                'category_ids'=>$categoryIds,'category_children'=>(int)($row['category_children']??1)===1?1:0,
                'starts'=>$starts,'ends'=>$ends,
                'apply_to_sale'=>(int)($row['apply_to_sale']??0)===1?1:0,
                'usage_limit'=>max(0,min(10000000,(int)($row['usage_limit']??0))),
                'customer_limit'=>max(0,min(1000000,(int)($row['customer_limit']??0))),
            ]];
        }
        if (!$coupons) throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
        if($allCategoryIds){
            $ids=array_map('intval',array_keys($allCategoryIds));
            $db=Factory::getContainer()->get(DatabaseInterface::class);
            $validIds=$db->setQuery($db->getQuery(true)->select('id')->from($db->quoteName('#__categories'))
                ->where('extension='.$db->quote('com_dharmashop'))->where('id IN ('.implode(',',$ids).')'))->loadColumn();
            if(count($validIds)!==count($ids))throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
        }
        return ['coupons'=>$coupons];
    }

    private static function normalizeDate(mixed $value,bool $isEnd): ?string
    {
        $text=trim((string)$value);
        if($text==='')return null;
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$text))$text.=$isEnd?' 23:59:59':' 00:00:00';
        elseif(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',$text))$text.=':00';
        elseif($isEnd&&preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/D',$text))$text=substr($text,0,10).' 23:59:59';
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$text);
        $errors=\DateTimeImmutable::getLastErrors();
        if(!$date||($errors&&($errors['warning_count']||$errors['error_count']))||$date->format('Y-m-d H:i:s')!==$text)throw new \DomainException('PLG_DHARMASHOPPROMOTION_COUPON_INVALID');
        return $text;
    }

    public function apply(object $promotion, Context $context): ?Result
    {
        $settings = (array)($promotion->params ?? []);
        $categoryIds=array_values(array_unique(array_filter(array_map('intval',(array)($settings['category_ids']??[])),static fn(int $id): bool=>$id>0)));
        $matchingProducts=null;
        if($categoryIds){
            $productIds=[];
            foreach($context->lines as $line)if($line->error===''&&(int)($line->product_id??0)>0)$productIds[(int)$line->product_id]=true;
            $matchingProducts=[];
            if($productIds){
                $db=Factory::getContainer()->get(DatabaseInterface::class);
                $sql='SELECT DISTINCT product_id FROM #__dharmashop_product_categories WHERE product_id IN ('.implode(',',array_map('intval',array_keys($productIds))).') AND catid IN ('.implode(',',$categoryIds).')';
                if((int)($settings['category_children']??1)!==1)$sql.=' AND direct=1';
                $matchingProducts=array_fill_keys(array_map('intval',$db->setQuery($sql)->loadColumn()),true);
            }
        }
        $minimumBase=0.0;$eligible=0.0;
        foreach ($context->lines as $line) {
            if ($line->error !== '') continue;
            if($matchingProducts!==null&&!isset($matchingProducts[(int)($line->product_id??0)]))continue;
            $minimumBase+=(float)$line->total;
            $sale = $line->base_price !== null && $line->unit_price !== null && (float)$line->base_price > (float)$line->unit_price + 0.0001;
            if ($sale && (int)($settings['apply_to_sale'] ?? 0)!==1) continue;
            $eligible += (float)$line->total;
        }
        if(round($minimumBase,2)<(float)($settings['minimum_subtotal']??0))return null;
        $eligible = round($eligible,2);
        if ($eligible <= 0) return null;
        $value = (float)($settings['discount_value'] ?? 0);
        $discount = (string)($settings['discount_type'] ?? 'percent') === 'amount' ? $value : round($eligible*$value/100,2);
        $maximum = (float)($settings['maximum_discount'] ?? 0);
        if ($maximum > 0) $discount = min($discount,$maximum);
        $discount = round(min($eligible,$discount),2);
        if ($discount <= 0) return null;
        $code = (string)$promotion->code;
        $title = Text::sprintf('PLG_DHARMASHOPPROMOTION_COUPON_DISCOUNT',$code);
        return new Result([['type'=>'discount','code'=>'promotion:'.$code,'title'=>$title,'amount'=>-$discount]],[
            'code'=>$code,'description'=>(string)($settings['description'] ?? ''),'discount'=>$discount,
        ]);
    }
}
