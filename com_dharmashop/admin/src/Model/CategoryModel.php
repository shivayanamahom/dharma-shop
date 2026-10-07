<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Одна категория любого типа (категория, бренд, бейдж) на штатной таблице категорий Joomla.
 * Тип задаёт параметр extension в адресе при создании и после создания не меняется.
 */
class CategoryModel extends AdminModel
{
    public $typeAlias = 'com_dharmashop.category';

    protected $text_prefix = 'COM_DHARMASHOP';

    private ?string $extension = null;

    public function getTable($name = 'Category', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /** Тип текущей записи: у существующей — из базы, у новой — из запроса. */
    public function getExtension(): string
    {
        if ($this->extension !== null) {
            return $this->extension;
        }

        $id = (int) $this->getState($this->getName() . '.id');

        if ($id > 0) {
            $db = $this->getDatabase();
            $stored = (string) $db->setQuery(
                $db->createQuery()->select('extension')->from($db->quoteName('#__categories'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER)
            )->loadResult();

            if (CategoryTypes::isValid($stored)) {
                return $this->extension = $stored;
            }
        }

        $input = Factory::getApplication()->getInput();
        $posted = (array) $input->get('jform', [], 'array');

        return $this->extension = CategoryTypes::normalize((string) ($posted['extension'] ?? $input->getString('extension', CategoryTypes::CATEGORY)));
    }

    protected function canDelete($record): bool
    {
        return !empty($record->id) && $record->published == -2
            && $this->getCurrentUser()->authorise('core.delete', $record->extension . '.category.' . (int) $record->id);
    }

    protected function canEditState($record): bool
    {
        $user = $this->getCurrentUser();

        return !empty($record->id)
            ? $user->authorise('core.edit.state', $record->extension . '.category.' . (int) $record->id)
            : $user->authorise('core.edit.state', 'com_dharmashop');
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.category.' . $this->getExtension(), 'category', ['control' => 'jform', 'load_data' => $loadData]);

        return empty($form) ? false : $form;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content'): void
    {
        $extension = $this->getExtension();
        $type = $extension === CategoryTypes::CATEGORY ? 'CATEGORY' : ($extension === CategoryTypes::BRAND ? 'BRAND' : 'BADGE');

        $form->setFieldAttribute('extension', 'default', $extension);
        $form->setFieldAttribute('parent_id', 'extension', $extension);
        $form->setFieldAttribute('show_in_list', 'label', 'COM_DHARMASHOP_CAT_SHOW_IN_LIST_' . $type . '_LABEL', 'params');

        if ($file = CategoryTypes::formFile($extension)) {
            $form->loadFile(JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/' . $file . '.xml', false);
        }

        if ($extension === CategoryTypes::BADGE) {
            // Бейдж — плоский справочник без страницы и настроек списка.
            $form->removeField('parent_id');
            $form->removeField('show_in_list', 'params');
            $form->removeField('list_limit', 'params');
            $form->removeField('list_ordering', 'params');
        }

        parent::preprocessForm($form, $data, $group);
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_dharmashop.edit.category.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            if (empty($data->id)) {
                $data->extension = $this->getExtension();
                $data->parent_id = (int) Factory::getApplication()->getInput()->getInt('parent_id', 1);
            }
        }

        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && !empty($item->id)) {
            $this->extension = CategoryTypes::normalize($item->extension);
            $item->metadata = is_array($item->metadata ?? null) ? $item->metadata : (new \Joomla\Registry\Registry($item->metadata ?? ''))->toArray();
        }

        return $item;
    }

    protected function prepareTable($table): void
    {
        // Положение в дереве меняем только у новой записи или при смене родителя.
        $old = clone $table;
        $old->load();

        if (empty($table->id) || (int) $table->parent_id !== (int) $old->parent_id) {
            $table->setLocation($table->parent_id ?: 1, 'last-child');
        }
    }

    public function save($data): bool
    {
        $id = (int) ($data['id'] ?? 0);
        $extension = $id > 0 ? $this->getExtensionOf($id) : CategoryTypes::normalize((string) ($data['extension'] ?? ''));
        $data['extension'] = $extension;
        $data['language'] = '*';

        if ($extension === CategoryTypes::BADGE) {
            $data['parent_id'] = 1;
        }

        // Родитель — корень или запись того же типа.
        $parentId = (int) ($data['parent_id'] ?? 1);

        if ($parentId > 1 && $this->getExtensionOf($parentId) !== $extension) {
            $this->setError(Text::_('COM_DHARMASHOP_ERROR_PARENT_TYPE'));

            return false;
        }

        $oldParent = $id > 0 ? $this->getParentOf($id) : null;

        if (!parent::save($data)) {
            return false;
        }

        $id = (int) $this->getState($this->getName() . '.id');
        $table = $this->getTable();
        $table->load($id);

        if (!$table->rebuildPath($table->id) || !$table->rebuild($table->id, $table->lft, $table->level, $table->path)) {
            $this->setError($table->getError());

            return false;
        }

        // Перемещение в дереве меняет родительские строки витринного индекса у товаров поддерева.
        if ($oldParent !== null && $oldParent !== (int) $table->parent_id) {
            Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->indexer()->rebuildCategorySubtree($id);
        }

        return true;
    }

    /** Удаление только пустых записей: без вложенных и без товаров (штатное удаление дерева удалило бы потомков). */
    public function delete(&$pks): bool
    {
        $db = $this->getDatabase();

        foreach (array_map('intval', (array) $pks) as $pk) {
            $children = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__categories')->where('parent_id = ' . $pk))->loadResult();
            $products = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__dharmashop_product_categories')->where('catid = ' . $pk))->loadResult();

            if ($children > 0 || $products > 0) {
                $this->setError(Text::sprintf('COM_DHARMASHOP_ERROR_CATEGORY_NOT_EMPTY', $pk));

                return false;
            }
        }

        return parent::delete($pks);
    }

    /** Перестраивает дерево и пути (кнопка «Перестроить»). */
    public function rebuild(): bool
    {
        $table = $this->getTable();

        if (!$table->rebuild()) {
            $this->setError($table->getError());

            return false;
        }

        return true;
    }

    public function saveorder($idArray = null, $lftArray = null)
    {
        $table = $this->getTable();

        if (!$table->saveorder($idArray, $lftArray)) {
            $this->setError($table->getError());

            return false;
        }

        return true;
    }

    private function getExtensionOf(int $id): string
    {
        $db = $this->getDatabase();

        return (string) $db->setQuery($db->createQuery()->select('extension')->from('#__categories')->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();
    }

    private function getParentOf(int $id): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery($db->createQuery()->select('parent_id')->from('#__categories')->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();
    }
}
