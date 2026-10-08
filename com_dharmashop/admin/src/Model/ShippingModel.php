<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Shipping\TypeRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Способ доставки: общие поля в Form XML, настройки типа подключаются из формы плагина (showon по полю plugin). */
class ShippingModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.shipping';

    public function getTable($name = 'Shipping', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.shipping', 'shipping', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        $id = (int) (((array) $data)['id'] ?? $this->getState('shipping.id'));

        // Тип существующего способа не меняется: настройки одного типа не подходят другому.
        if ($id > 0) {
            $table = $this->getTable();

            if ($table->load($id)) {
                $form->setValue('plugin', null, (string) $table->plugin);
                $form->setFieldAttribute('plugin', 'disabled', 'true');
                $form->setFieldAttribute('plugin', 'filter', 'unset');
                $form->setFieldAttribute('plugin', 'required', 'false');
            }
        }

        if (!$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            foreach (['state', 'ordering', 'is_default'] as $name) {
                $form->setFieldAttribute($name, 'disabled', 'true');
                $form->setFieldAttribute($name, 'filter', 'unset');
            }
        }

        return $form;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content'): void
    {
        foreach (TypeRegistry::current()->all() as $type) {
            $form->loadFile($type->settingsPath(), false);
        }

        parent::preprocessForm($form, $data, $group);
    }

    protected function loadFormData(): mixed
    {
        return Factory::getApplication()->getUserState('com_dharmashop.edit.shipping.data', []) ?: $this->getItem();
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item) {
            // AdminModel уже раскладывает params в массив; строку (старые данные) разбираем сами.
            $params = is_array($item->params) ? $item->params : (new Registry((string) $item->params))->toArray();
            // В форме настройки типа лежат в своей группе params[<тип>]: поля разных типов могут называться одинаково.
            $item->params = $item->plugin ? [(string) $item->plugin => $params] : [];
        }

        return $item;
    }

    public function save($data)
    {
        $data = array_intersect_key((array) $data, array_flip(['id', 'title', 'alias', 'plugin', 'description', 'image', 'params', 'is_default', 'state', 'access', 'ordering']));
        $id   = (int) ($data['id'] ?? 0);

        if (!$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            unset($data['state'], $data['ordering'], $data['is_default']);
        }

        if ($id > 0) {
            $data['plugin'] = $this->loadPlugin($id);
        }

        $type = TypeRegistry::current()->find((string) ($data['plugin'] ?? ''));

        if ($type === null) {
            $this->setError(Text::_('COM_DHARMASHOP_SHIPPING_TYPE_REQUIRED'));

            return false;
        }

        try {
            $settings = $type->normalizeSettings((array) (((array) ($data['params'] ?? []))[$type->id()] ?? []));
        } catch (\DomainException $error) {
            $this->setError(Text::_($error->getMessage()));

            return false;
        }

        $data['params'] = (new Registry($settings))->toString();

        return parent::save($data);
    }

    /**
     * Делает способ единственным способом по умолчанию (или снимает отметку). Назначить можно только опубликованный способ;
     * остальные снимаются одним UPDATE.
     */
    public function setDefault(int $id, bool $default): bool
    {
        $table = $this->getTable();

        if ($id < 1 || !$table->load($id)) {
            $this->setError(Text::_('JERROR_NO_ITEMS_SELECTED'));

            return false;
        }

        if ($default && (int) $table->state !== 1) {
            $this->setError(Text::_('COM_DHARMASHOP_SHIPPING_DEFAULT_PUBLISHED'));

            return false;
        }

        $db = $this->getDatabase();
        $flag = $default ? 1 : 0;
        $query = $db->createQuery()->update($db->quoteName('#__dharmashop_shipping_methods'))
            ->set('is_default = CASE WHEN id = :id THEN :flag ELSE 0 END')
            ->where('is_default = 1 OR id = :target')
            ->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':flag', $flag, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':target', $id, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        return true;
    }

    private function loadPlugin(int $id): string
    {
        $table = $this->getTable();
        $table->load($id);

        return (string) $table->plugin;
    }

    protected function canDelete($record): bool
    {
        return !empty($record->id) && $this->getCurrentUser()->authorise('core.delete', $this->option);
    }
}
