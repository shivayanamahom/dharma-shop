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

use Dharma\Component\DharmaShop\Administrator\Image\ImageCleaner;
use Dharma\Component\DharmaShop\Administrator\Order\InheritHints;
use Dharma\Component\DharmaShop\Administrator\Seo\SeoHints;
use Dharma\Component\DharmaShop\Administrator\Seo\SeoSettings;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Component\ComponentHelper;
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

    /**
     * Пакетные операции окна на списке категорий/брендов/бейджей: имя поля => метод. Расширяется строкой здесь,
     * методом batch<Имя> и полем в tmpl/categories/default_batch_body.php.
     */
    protected $batch_copymove = '';

    protected $batch_commands = [
        'assetgroup_id' => 'batchAccess',
        'parent_id' => 'batchMoveParent',
        'show_in_list' => 'batchShowInList',
        'list_ordering' => 'batchListOrdering',
        'list_limit' => 'batchListLimit',
    ];

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

        if ($extension === CategoryTypes::CATEGORY) {
            // Подсказки «Наследуется: …»: для существующей — без её собственных значений, для новой — по родителю.
            $rules = Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->orderRules();
            $id = (int) (is_array($data) ? ($data['id'] ?? 0) : ($data->id ?? 0));
            $parent = (int) (is_array($data) ? ($data['parent_id'] ?? 0) : ($data->parent_id ?? 0));
            $inherited = $id > 0 ? $rules->forCategory($id, false) : $rules->forCategory($parent > 1 ? $parent : 0);
            InheritHints::apply($form, $rules, $inherited, 'params', 'order_');
        } else {
            // Правила оформления заказа есть только у категорий магазина.
            foreach (['order_qty_min', 'order_qty_step', 'order_qty_max', 'order_unit', 'order_stock_tracked', 'order_backorder'] as $field) {
                $form->removeField($field, 'params');
            }
        }

        // Подсказки SEO: в пустых полях виден шаблон из настроек магазина.
        SeoHints::apply($form, new SeoSettings(ComponentHelper::getParams('com_dharmashop')), $extension === CategoryTypes::BRAND ? 'brand' : ($extension === CategoryTypes::BADGE ? 'badge' : 'category'));

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
        $oldOrderParams = $id > 0 ? $this->orderParams($this->paramsOf($id)) : [];

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

        $this->applyOrderRules($id, $oldOrderParams, $table);

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

        // Картинки удаляемых записей запоминаем до удаления: после него записей в базе уже нет.
        $files = [];

        foreach (array_map('intval', (array) $pks) as $pk) {
            $params = json_decode((string) $db->setQuery($db->createQuery()->select('params')->from('#__categories')->where('id = ' . $pk))->loadResult(), true) ?: [];

            foreach (['image', 'icon'] as $key) {
                if (!empty($params[$key])) {
                    $files[] = (string) $params[$key];
                }
            }
        }

        if (!parent::delete($pks)) {
            return false;
        }

        // После удаления убираем только свои файлы, на которые больше никто не ссылается (правила — ImageCleaner).
        $cleaner = new ImageCleaner($db, JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));

        foreach (array_unique($files) as $file) {
            $cleaner->deleteIfUnused($file);
        }

        return true;
    }

    /**
     * Число товаров, привязанных к записи (по витрине; для категории включая подкатегории): всего (без корзины),
     * опубликованных и опубликованных в наличии.
     *
     * @return  array{total: int, published: int, in_stock: int}
     */
    public function getProductCounts(int $id): array
    {
        if ($id <= 0) {
            return ['total' => 0, 'published' => 0, 'in_stock' => 0];
        }

        $db = $this->getDatabase();
        $row = $db->setQuery(
            $db->createQuery()
                ->select('COALESCE(SUM(state >= 0), 0) AS total, COALESCE(SUM(state = 1), 0) AS published, COALESCE(SUM(state = 1 AND in_stock = 1), 0) AS in_stock')
                ->from('#__dharmashop_product_categories')->where('catid = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        return ['total' => (int) ($row->total ?? 0), 'published' => (int) ($row->published ?? 0), 'in_stock' => (int) ($row->in_stock ?? 0)];
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

    /** @return array<string, mixed> параметры записи (JSON → массив) */
    private function paramsOf(int $id): array
    {
        $db = $this->getDatabase();

        return json_decode((string) $db->setQuery($db->createQuery()->select('params')->from('#__categories')->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult(), true) ?: [];
    }

    /** @return array<string, string> только правила оформления заказа */
    private function orderParams(array $params): array
    {
        $result = [];

        foreach (['order_qty_min', 'order_qty_step', 'order_qty_max', 'order_unit', 'order_stock_tracked', 'order_backorder'] as $key) {
            $result[$key] = (string) ($params[$key] ?? '');
        }

        return $result;
    }

    /**
     * Если у категории изменились правила оформления заказа, пересчитывает учёт остатков у товаров этой ветки
     * (сразу, если их не больше 5000; иначе просит запустить команду обслуживания).
     */
    private function applyOrderRules(int $id, array $before, Table $table): void
    {
        if ($table->extension !== CategoryTypes::CATEGORY) {
            return;
        }

        $after = $this->orderParams(json_decode((string) $table->params, true) ?: []);

        if ($after === $before) {
            return;
        }

        $app = Factory::getApplication();
        $shop = $app->bootComponent('com_dharmashop')->getServices();
        $rules = $shop->orderRules();
        $rules->flush();
        $ids = $rules->subtree($id);
        $db = $this->getDatabase();
        $count = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__dharmashop_products')->where('catid IN (' . implode(',', array_map('intval', $ids)) . ')'))->loadResult();

        if ($count > 5000) {
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_ORDER_RECALC_NEEDED'), 'warning');

            return;
        }

        if ($count > 0) {
            $rules->materializeCategories($ids);
            $shop->rebuilder()->rebuildCategories($ids);
            $shop->indexer()->refreshCategories($ids);
            $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_ORDER_RECALC_DONE', $count), 'message');
        }
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

    // ------------------------------------------------------------------ пакетная обработка

    /** Разворачивает группы блоков окна (forms/batch_category.xml) в плоский набор команд для AdminModel::batch. */
    public function batch($commands, $pks, $contexts)
    {
        $flat = [];

        foreach ((array) $commands as $key => $value) {
            if (is_array($value) && in_array($key, ['access', 'parent', 'display', 'products'], true)) {
                $flat = array_merge($flat, $value);
            } else {
                $flat[$key] = $value;
            }
        }

        return parent::batch($flat, $pks, $contexts);
    }

    /**
     * Применяет изменение к каждой выбранной записи, на правку которой есть право (ресурс «<тип>.category.<id>»).
     *
     * @param   int[]     $pks
     * @param   callable  $change  (Table $table, int $id): bool — true, если запись изменена и её надо сохранить
     */
    private function batchEach(array $pks, array $contexts, callable $change): bool
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        foreach (array_map('intval', $pks) as $pk) {
            $table = $this->getTable();

            if (!$table->load($pk)) {
                continue;
            }

            $asset = ($contexts[$pk] ?? '') !== '' ? $contexts[$pk] : $table->extension . '.category.' . $pk;

            if (!$user->authorise('core.edit', $asset)) {
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_ENTRY_SKIP_NO_ACCESS', $table->title), 'warning');
                continue;
            }

            try {
                if ($change($table, $pk) && !$table->store()) {
                    throw new \RuntimeException($table->getError());
                }
            } catch (\RuntimeException $e) {
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_ENTRY_SKIP', $table->title, Text::_($e->getMessage())), 'warning');
            }
        }

        return true;
    }

    /** Изменяет одно значение в параметрах записи. */
    private function batchParam(array $pks, array $contexts, string $key, string|int $value): bool
    {
        return $this->batchEach($pks, $contexts, function (Table $table) use ($key, $value): bool {
            $params = new \Joomla\Registry\Registry($table->params);

            if ((string) $params->get($key, '') === (string) $value) {
                return false;
            }

            $params->set($key, $value);
            $table->params = (string) $params;

            return true;
        });
    }

    /** Переносит записи под нового родителя того же типа (1 — в корень); вложенные переезжают вместе с записью. */
    protected function batchMoveParent($value, $pks, $contexts): bool
    {
        $parent = (int) $value;
        $db = $this->getDatabase();
        $services = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
        $moved = [];

        $result = $this->batchEach((array) $pks, (array) $contexts, function (Table $table, int $pk) use ($parent, $db, &$moved): bool {
            if ((int) $table->parent_id === $parent) {
                return false;
            }

            if ($table->extension === CategoryTypes::BADGE) {
                throw new \RuntimeException('COM_DHARMASHOP_BATCH_ERROR_BADGE_FLAT');
            }

            if ($parent > 1) {
                $target = $db->setQuery($db->createQuery()->select('extension, lft, rgt')->from('#__categories')->where('id = ' . $parent))->loadObject();

                if (!$target || $target->extension !== $table->extension) {
                    throw new \RuntimeException('COM_DHARMASHOP_ERROR_PARENT_TYPE');
                }

                if ($parent === $pk || ($target->lft >= $table->lft && $target->rgt <= $table->rgt)) {
                    throw new \RuntimeException('COM_DHARMASHOP_BATCH_ERROR_INTO_SELF');
                }

                if (!Factory::getApplication()->getIdentity()->authorise('core.create', $table->extension . '.category.' . $parent)) {
                    throw new \RuntimeException('COM_DHARMASHOP_BATCH_ERROR_CREATE_DENIED');
                }
            }

            $table->setLocation($parent, 'last-child');
            $table->parent_id = $parent;
            $moved[] = $pk;

            return true;
        });

        foreach ($moved as $pk) {
            $table = $this->getTable();
            $table->load($pk);
            $table->rebuildPath($table->id);
            $table->rebuild($table->id, $table->lft, $table->level, $table->path);
            $services->indexer()->rebuildCategorySubtree($pk);
        }

        return $result;
    }

    /** «Показывать в списке» (0/1). */
    protected function batchShowInList($value, $pks, $contexts): bool
    {
        return $this->batchParam((array) $pks, (array) $contexts, 'show_in_list', (int) ((string) $value === 'yes'));
    }

    /** Сортировка товаров по умолчанию (значения из списка формы; `global` — как в настройках магазина). */
    protected function batchListOrdering($value, $pks, $contexts): bool
    {
        $allowed = ['order', 'price_asc', 'price_desc', 'new', 'title', 'global'];

        if (!in_array((string) $value, $allowed, true)) {
            $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE'));

            return false;
        }

        return $this->batchParam((array) $pks, (array) $contexts, 'list_ordering', (string) $value === 'global' ? '' : (string) $value);
    }

    /** Число товаров на странице (0 — как в настройках магазина). */
    protected function batchListLimit($value, $pks, $contexts): bool
    {
        $limit = (string) $value === 'global' ? 0 : (int) $value;

        if ($limit < 0 || $limit > 200) {
            $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE'));

            return false;
        }

        return $this->batchParam((array) $pks, (array) $contexts, 'list_limit', $limit);
    }
}
