<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;

class CustomerModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.customer';
    public function getTable($name = 'Customer', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.customer', 'customer', ['control'=>'jform','load_data'=>$loadData]);
        if ($form && !$this->getCurrentUser()->authorise((int) $form->getValue('id') > 0 ? 'core.edit' : 'core.create', $this->option)) {
            foreach (['name','email','phone','note'] as $field) $form->setFieldAttribute($field, 'readonly', 'true');
        }
        return $form ?: false;
    }
    protected function loadFormData(): mixed
    {
        return Factory::getApplication()->getUserState('com_dharmashop.edit.customer.data', []) ?: $this->getItem();
    }
    public function save($data)
    {
        // Связь с Joomla задаёт только создание заказа после входа, не POST администратора.
        return parent::save(array_intersect_key((array) $data, array_flip(['id','name','email','phone','note'])));
    }
    public function recentOrders(int $id): array
    {
        if ($id < 1 || !$this->getCurrentUser()->authorise('dharmashop.orders.manage', $this->option)) return [];
        $db = $this->getDatabase();
        $query = $db->createQuery()->select(['o.id','o.number','o.created','o.total','o.currency','s.title AS status_title'])
            ->from($db->quoteName('#__dharmashop_orders','o'))
            ->join('LEFT', $db->quoteName('#__dharmashop_statuses','s') . ' ON s.id=o.status_id')
            ->where('o.customer_id=:id')->bind(':id', $id, ParameterType::INTEGER)->order('o.id DESC');
        return $db->setQuery($query, 0, 20)->loadObjectList();
    }
    public function userName(?int $id): string
    {
        if (!$id) return '';
        $db = $this->getDatabase();
        return (string) $db->setQuery($db->createQuery()->select('username')->from($db->quoteName('#__users'))
            ->where('id=:id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();
    }
    protected function canDelete($record): bool { return false; }
}
