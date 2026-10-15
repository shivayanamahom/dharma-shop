<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Dharma\Component\DharmaShop\Administrator\Promotion\TypeRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class PromotionModel extends AdminModel
{
    protected $option='com_dharmashop'; protected $text_prefix='COM_DHARMASHOP'; public $typeAlias='com_dharmashop.promotion';
    public function getTable($name='Promotion',$prefix='Administrator',$options=[]): Table { return parent::getTable($name,$prefix,$options); }
    public function getForm($data=[],$loadData=true): Form|false
    {
        $form=$this->loadForm('com_dharmashop.promotion','promotion',['control'=>'jform','load_data'=>$loadData]); if(!$form)return false;
        $id=(int)(((array)$data)['id']??$this->getState('promotion.id'));
        if($id>0){$table=$this->getTable();if($table->load($id)){$form->setValue('plugin',null,(string)$table->plugin);$form->setFieldAttribute('plugin','disabled','true');$form->setFieldAttribute('plugin','filter','unset');$form->setFieldAttribute('plugin','required','false');}}
        $pluginId=(string)(((array)$data)['plugin']??$form->getValue('plugin')??'');$type=TypeRegistry::current()->find($pluginId);
        if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option)) foreach(['state','ordering'] as $name){$form->setFieldAttribute($name,'disabled','true');$form->setFieldAttribute($name,'filter','unset');}
        return $form;
    }
    protected function preprocessForm(Form $form,$data,$group='content'): void { foreach(TypeRegistry::current()->all() as $type)$form->loadFile($type->settingsPath(),false); parent::preprocessForm($form,$data,$group); }
    protected function loadFormData(): mixed
    {
        $app=Factory::getApplication();
        $userData=$app->getUserState('com_dharmashop.edit.promotion.data',[]);
        if(!$userData){$item=$this->getItem();if($item&&(int)$item->id<1&&!$item->plugin)$item->plugin='threshold';return $item;}
        $id=(int)($userData['id']??$app->getInput()->getInt('id'));
        if($id<1)return $userData;
        $item=$this->getItem($id);
        if(!$item)return $userData;
        $data=array_replace((array)$item,(array)$userData);
        $data['plugin']=(string)$item->plugin;
        if($item->plugin==='coupon'){
            $stored=(array)($item->params['coupons']??[]);
            $posted=(array)($userData['params']['coupons']??$stored);
            foreach($posted as $index=>&$coupon){
                if(!is_array($coupon))continue;
                $fallback=$stored[$index]??[];
                foreach(['starts','ends'] as $date){
                    if(!array_key_exists($date,$coupon))$coupon[$date]=$userData[$date]??($fallback[$date]??null);
                }
            }
            unset($coupon);
            $data['params']=array_replace((array)$item->params,(array)($userData['params']??[]));
            $data['params']['coupons']=$posted;
        }
        return $data;
    }
    public function getItem($pk=null)
    {
        $item=parent::getItem($pk);
        if($item){
            if ($item->params instanceof Registry) {
                $item->params = $item->params->toArray();
            } elseif (!is_array($item->params)) {
                $item->params = (new Registry((string) ($item->params ?? '')))->toArray();
            }
            if((int)$item->id>0 && $item->plugin==='coupon'){
                $item->params=[];
                $rows=$this->getDatabase()->setQuery($this->getDatabase()->getQuery(true)->select(['code','params','ordering'])->from($this->getDatabase()->quoteName('#__dharmashop_promotion_codes'))->where('promotion_id='.(int)$item->id)->order('ordering,id'))->loadObjectList();
                foreach($rows as $row){$settings=(new Registry((string)$row->params))->toArray();if(!array_key_exists('starts',$settings))$settings['starts']=$item->starts;if(!array_key_exists('ends',$settings))$settings['ends']=$item->ends;$item->params['coupons'][]=['code'=>$row->code,'ordering'=>(int)$row->ordering]+$settings;}
            }
            if($item->plugin==='threshold' && !empty($item->params['tiers'])){
                foreach($item->params['tiers'] as &$tier){
                    if(!is_array($tier))continue;
                    if(!array_key_exists('discount_value',$tier))$tier['discount_value']=$tier['discount_percent']??'';
                    if(!array_key_exists('discount_type',$tier))$tier['discount_type']='percent';
                    unset($tier['discount_percent']);
                }
                unset($tier);
            }
            if(empty($item->params['coupons'])) $item->params['coupons']=[['code'=>'','description'=>'','discount_type'=>'percent','discount_value'=>'','minimum_subtotal'=>0,'maximum_discount'=>0,'apply_to_sale'=>0,'usage_limit'=>0,'customer_limit'=>0]];
        }
        return $item;
    }
    public function save($data)
    {
        $data=array_intersect_key((array)$data,array_flip(['id','title','plugin','code','state','access','ordering','starts','ends','params']));
        $id=(int)($data['id']??0); $legacyStarts=null; $legacyEnds=null; if(!$this->getCurrentUser()->authorise('core.edit.state',$this->option))unset($data['state'],$data['ordering']);
        if($id>0){$table=$this->getTable();if(!$table->load($id))return false;$data['plugin']=(string)$table->plugin;$legacyStarts=$table->starts;$legacyEnds=$table->ends;}
        if((string)($data['plugin']??'')==='coupon'){$params=(array)($data['params']??[]);if(isset($params['coupons'])&&is_array($params['coupons']))foreach($params['coupons'] as &$coupon){if(!is_array($coupon))continue;if(!array_key_exists('starts',$coupon))$coupon['starts']=$legacyStarts;if(!array_key_exists('ends',$coupon))$coupon['ends']=$legacyEnds;}unset($coupon);$data['params']=$params;$data['starts']=null;$data['ends']=null;}
        $type=TypeRegistry::current()->find((string)($data['plugin']??'')); if(!$type){$this->setError(Text::_('COM_DHARMASHOP_PROMOTION_TYPE_REQUIRED'));return false;}
        try{$settings=$type->normalizeSettings((array)($data['params']??[]));}catch(\DomainException $e){$this->setError(Text::_($e->getMessage()));return false;}
        $couponRows=(array)($settings['coupons']??[]);
        unset($settings['coupons']);
        $data['code']=null; $data['params']=(new Registry($settings))->toString();
        $db=$this->getDatabase(); $db->transactionStart();
        try{
            foreach($couponRows as $coupon){
                $query=$db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_promotion_codes'))->where('code='.$db->quote($coupon['code']))->where('promotion_id<>'.max(0,$id));
                $duplicate=(int)$db->setQuery($query,0,1)->loadResult();
                if($duplicate){$db->transactionRollback();$this->setError(Text::_('COM_DHARMASHOP_PROMOTION_CODE_EXISTS'));return false;}
            }
            if(!parent::save($data)){$db->transactionRollback();return false;}
            $promotionId=(int)($data['id']??0);
            if($promotionId<1){$promotionId=(int)$this->getState($this->getName().'.id');}
            if($promotionId<1) throw new \RuntimeException('Не удалось определить сохранённую акцию.');
            $db->setQuery($db->createQuery()->delete($db->quoteName('#__dharmashop_promotion_codes'))->where('promotion_id='.(int)$promotionId))->execute();
            foreach($couponRows as $coupon){
                $codeRow=(object)['promotion_id'=>$promotionId,'code'=>$coupon['code'],'params'=>(new Registry($coupon['params']))->toString(),'ordering'=>(int)$coupon['ordering']];
                $db->insertObject('#__dharmashop_promotion_codes',$codeRow);
            }
            $db->transactionCommit(); return true;
        }catch(\Throwable $error){$db->transactionRollback();$this->setError($error->getMessage());return false;}
    }
}
