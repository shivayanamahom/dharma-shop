<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
\defined('_JEXEC') or die;
class StatusModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.status';
    public function getTable($name = 'Status', $prefix = 'Administrator', $options = []): Table { return parent::getTable($name,$prefix,$options); }
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.status','status',['control'=>'jform','load_data'=>$loadData]);
        if ($form && !$this->getCurrentUser()->authorise('core.edit.state',$this->option)) {
            foreach (['state','ordering','is_default'] as $field) { $form->setFieldAttribute($field,'disabled','true'); $form->setFieldAttribute($field,'filter','unset'); }
        }
        return $form ?: false;
    }
    protected function loadFormData(): mixed { return Factory::getApplication()->getUserState('com_dharmashop.edit.status.data',[]) ?: $this->getItem(); }
    public function save($data)
    {
        $data = array_intersect_key((array) $data,array_flip(['id','title','alias','color','ordering','state','is_default','customer_message','stock_effect']));
        if (!$this->getCurrentUser()->authorise('core.edit.state',$this->option)) {
            unset($data['state'],$data['ordering'],$data['is_default'],$data['stock_effect']);
        }
        return parent::save($data);
    }
    protected function prepareTable($table): void
    {
        if (!$table->id) { $table->stock_effect = 0; $table->is_final = 0; $table->notify_customer = 0; $table->notify_admin = 0; }
    }
    protected function canDelete($record): bool { return false; }
}
