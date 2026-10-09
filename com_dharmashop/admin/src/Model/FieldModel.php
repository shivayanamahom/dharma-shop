<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Model;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class FieldModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.field';
    public function getTable($name = 'Field', $prefix = 'Administrator', $options = []): Table { return parent::getTable($name, $prefix, $options); }
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.field', 'field', ['control' => 'jform', 'load_data' => $loadData]);
        if (!$form) { return false; }
        $id = (int) (((array) $data)['id'] ?? $this->getState('field.id'));
        if ($id > 0) {
            $table = $this->getTable();
            if ($table->load($id)) {
                $form->setValue('target', null, (string) $table->target);
                $form->setFieldAttribute('target', 'disabled', 'true');
                $form->setFieldAttribute('target', 'filter', 'unset');
                $form->setFieldAttribute('target', 'required', 'false');
                $form->setValue('type', null, (string) $table->type);
                $form->setFieldAttribute('type', 'disabled', 'true');
                $form->setFieldAttribute('type', 'filter', 'unset');
                $form->setFieldAttribute('type', 'required', 'false');
            }
        }
        if (!$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            foreach (['state','ordering'] as $name) { $form->setFieldAttribute($name, 'disabled', 'true'); $form->setFieldAttribute($name, 'filter', 'unset'); }
        }
        return $form;
    }
    protected function preprocessForm(Form $form, $data, $group = 'content'): void
    {
        foreach (TypeRegistry::current()->all() as $type) { $form->loadFile($type->settingsPath(), false); }
        parent::preprocessForm($form, $data, $group);
    }
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);
        if (!$item) { return false; }
        $item->params = (new Registry($item->params))->toArray();
        // Effective configuration also covers definitions saved before editor card display was disabled.
        if ($item->type === 'editor') {
            $item->params['load_card'] = 0;
            $item->params['format_card'] = 'comma';
            $item->in_card = 0;
        }
        if ($item->type === 'gallery') {
            $item->params = array_replace($item->params, TypeRegistry::current()->get('gallery')->normalizeSettings($item->params));
            $item->params['load_card'] = 0;
            $item->in_card = 0;
        }
        if (in_array((string) $item->type, ['editor', 'text', 'files', 'gallery'], true)) { $item->filterable = 0; }
        $id = (int) $item->id;
        $db = $this->getDatabase();
        $query = $db->createQuery()->select($db->quoteName(['id','title','alias','image']))->from($db->quoteName('#__dharmashop_field_options'))
            ->where('field_id = :id')->bind(':id', $id, ParameterType::INTEGER)->order('ordering ASC, id ASC');
        $item->options = $db->setQuery($query)->loadAssocList();
        if ($item->type === 'checkbox' && !empty($item->params['checkbox_default_option_id'])) {
            $titles = array_column($item->options, 'title', 'id');
            $item->params['checkbox_default_option'] = $titles[(int) $item->params['checkbox_default_option_id']] ?? '';
        }
        $query = $db->createQuery()->select('catid')->from($db->quoteName('#__dharmashop_category_fields'))->where('field_id = :id')->bind(':id', $id, ParameterType::INTEGER);
        $item->catids = $db->setQuery($query)->loadColumn();
        return $item;
    }
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_dharmashop.edit.field.data', []);
        if (!$data) {
            $data = $this->getItem();
            if ($data && empty($data->id)) {
                $filters = (array) Factory::getApplication()->getUserState('com_dharmashop.fields.filter', []);
                $data->target = in_array($filters['target'] ?? '', ['product','category'], true) ? $filters['target'] : 'product';
                $data->type = 'text';
                $data->ordering = $this->nextOrdering($data->target);
            }
        }
        return $data;
    }
    protected function prepareTable($table): void
    {
        if (!$table->id && (int) $table->ordering < 1) { $table->ordering = $this->nextOrdering((string) $table->target); }
    }
    private function nextOrdering(string $target): int
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select('MAX(ordering)')->from($db->quoteName('#__dharmashop_fields'))->where('target = :target')->bind(':target', $target);
        return max(1, (int) $db->setQuery($query)->loadResult() + 1);
    }
    public function save($data)
    {
        $db = $this->getDatabase();
        $id = (int) ($data['id'] ?? $this->getState('field.id'));
        $existing = $id > 0 ? $this->getItem($id) : null;
        $snapshot = null;
        if ($id > 0) {
            $table = $this->getTable();
            if ($table->load($id)) {
                $snapshot = new \stdClass();
                foreach (array_keys($db->getTableColumns($db->replacePrefix('#__dharmashop_fields'), false)) as $column) {
                    $snapshot->$column = $table->$column;
                }
            }
        }
        $type = (string) ($data['type'] ?? ($existing->type ?? 'text'));
        if (in_array($type, ['editor', 'text', 'files', 'gallery'], true)) { $data['filterable'] = 0; }
        if ($type === 'gallery') {
            $data['params']['load_card'] = 0;
            $data['in_card'] = 0;
        }
        try {
            $options = [];
            $seen = [];
            $seenIds = [];
            $existingIds = array_column($existing->options ?? [], 'id');
            $existingImages = array_column($existing->options ?? [], 'image', 'id');
            $defaultOptionIndex = null;
            if (in_array($type, ['list','checkbox'], true)) {
                foreach ((array) ($data['options'] ?? []) as $row) {
                    $row = (array) $row;
                    $title = trim(strip_tags((string) ($row['title'] ?? '')));
                    $optionId = (int) ($row['id'] ?? 0);
                    if ($title === '' && !$optionId) { continue; }
                    // An already-open older form has no image key; retain its saved media reference.
                    $image = trim((string) ($row['image'] ?? ($existingImages[$optionId] ?? '')));
                    if (mb_strlen($image) > 500 || preg_match('/[\x00-\x1f\x7f]/', $image)) {
                        throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_OPTION_IMAGE_INVALID');
                    }
                    $alias = ApplicationHelper::stringURLSafe(trim((string) ($row['alias'] ?? '')) ?: $title);
                    if ($title === '' || $alias === '' || mb_strlen($title) > 255 || mb_strlen($alias) > 190 || isset($seen[$alias])
                        || ($optionId && (isset($seenIds[$optionId]) || !in_array($optionId, array_map('intval', $existingIds), true)))) {
                        throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_OPTIONS_INVALID');
                    }
                    $seen[$alias] = true;
                    if ($optionId) { $seenIds[$optionId] = true; }
                    $options[] = ['id'=>$optionId, 'title'=>$title, 'alias'=>$alias, 'image'=>$image];
                }
                if (($type === 'list' || ($data['params']['checkbox_mode'] ?? 'single') === 'options') && !$options) {
                    throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_OPTIONS_REQUIRED');
                }
            }
            if ($type === 'checkbox') {
                // Never accept an option ID from the client as the default selection.
                $data['params']['checkbox_default_option_id'] = 0;
                if (($data['params']['checkbox_mode'] ?? 'single') === 'options') {
                    $title = trim(strip_tags((string) ($data['params']['checkbox_default_option'] ?? '')));
                    $data['params']['checkbox_default_option'] = $title;
                    if ($title !== '') {
                        $matches = array_keys(array_filter($options, static fn (array $option): bool => $option['title'] === $title));
                        // Preserve a stable default when its option is renamed in the same save.
                        if (!$matches && $title === ($existing->params['checkbox_default_option'] ?? '')) {
                            $previousId = (int) ($existing->params['checkbox_default_option_id'] ?? 0);
                            if ($previousId > 0) {
                                $matches = array_keys(array_filter($options, static fn (array $option): bool => $option['id'] === $previousId));
                            }
                        }
                        if (count($matches) !== 1) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_CHECKBOX_DEFAULT_OPTION_INVALID'); }
                        $defaultOptionIndex = $matches[0];
                        $data['params']['checkbox_default_option'] = $options[$defaultOptionIndex]['title'];
                        $data['params']['checkbox_default_option_id'] = $options[$defaultOptionIndex]['id'];
                    }
                }
            }
            $retained = array_column($options, 'id');
            foreach ($existingIds as $optionId) {
                if (!in_array((int) $optionId, $retained, true)) {
                    foreach (['field_values', 'category_field_values'] as $storage) {
                        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_' . $storage))
                            ->where('option_id = :option')->bind(':option', $optionId, ParameterType::INTEGER);
                        if ($db->setQuery($query, 0, 1)->loadResult()) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_OPTION_USED'); }
                    }
                }
            }
            $catids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['catids'] ?? [])), static fn ($id) => $id > 1)));
            if ((int) ($data['all_categories'] ?? 1) === 0) {
                if (!$catids) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_CATEGORIES_REQUIRED'); }
                $extension = 'com_dharmashop';
                $query = $db->createQuery()->select('id')->from($db->quoteName('#__categories'))->whereIn('id', $catids)->where('extension = :extension')->bind(':extension', $extension);
                if (count($db->setQuery($query)->loadColumn()) !== count($catids)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_CATEGORIES_INVALID'); }
            } else { $catids = []; }
        } catch (\Throwable $error) { $this->setError(Text::_($error->getMessage())); return false; }
        unset($data['options'], $data['catids']);
        if (!parent::save($data)) { return false; }
        $id = (int) $this->getState('field.id');
        // Table::store takes LOCK TABLES: the following relation transaction starts only after it completes.
        $db->transactionStart();
        try {
            foreach ($options as $ordering => $option) {
                $record = (object) ['field_id'=>$id, 'title'=>$option['title'], 'alias'=>$option['alias'], 'ordering'=>$ordering + 1];
                // Prevent temporary alias collisions when two existing options swap aliases.
                if ($option['id']) {
                    $record->id = $option['id'];
                    $temporary = '__dharma_edit_' . $id . '_' . $record->id;
                    $query = $db->createQuery()->update($db->quoteName('#__dharmashop_field_options'))->set('alias = :alias')->where('id = :option')
                        ->bind(':alias', $temporary)->bind(':option', $record->id, ParameterType::INTEGER);
                    $db->setQuery($query)->execute();
                }
            }
            foreach ($existingIds as $optionId) {
                if (!in_array((int) $optionId, $retained, true)) {
                    $query = $db->createQuery()->delete($db->quoteName('#__dharmashop_field_options'))->where('id = :option')->bind(':option', $optionId, ParameterType::INTEGER);
                    $db->setQuery($query)->execute();
                }
            }
            foreach ($options as $ordering => $option) {
                $record = (object) ['field_id'=>$id,'title'=>$option['title'],'alias'=>$option['alias'],'image'=>$option['image'],'ordering'=>$ordering+1];
                if ($option['id']) { $record->id=$option['id']; $db->updateObject('#__dharmashop_field_options',$record,'id'); }
                else { $db->insertObject('#__dharmashop_field_options',$record,'id'); }
                if ($defaultOptionIndex === $ordering) {
                    // New options receive IDs here, after the definition's native Table::store.
                    $query = $db->createQuery()->select('params')->from($db->quoteName('#__dharmashop_fields'))
                        ->where('id = :id')->bind(':id', $id, ParameterType::INTEGER);
                    $settings = new Registry($db->setQuery($query)->loadResult());
                    $settings->set('checkbox_default_option_id', (int) $record->id);
                    $db->updateObject('#__dharmashop_fields', (object) ['id' => $id, 'params' => (string) $settings], 'id');
                }
            }
            $query = $db->createQuery()->delete($db->quoteName('#__dharmashop_category_fields'))->where('field_id = :id')->bind(':id', $id, ParameterType::INTEGER);
            $db->setQuery($query)->execute();
            foreach ($catids as $catid) { $db->insertObject('#__dharmashop_category_fields',(object) ['catid'=>$catid,'field_id'=>$id]); }
            $db->transactionCommit();
        } catch (\Throwable $error) {
            $db->transactionRollback();
            // Restore the definition as well: its Table::store cannot share the relation transaction.
            try {
                if ($snapshot !== null) {
                    $db->updateObject('#__dharmashop_fields', $snapshot, 'id', true);
                } else {
                    $this->getTable()->delete($id);
                    $this->setState('field.id', 0);
                }
            } catch (\Throwable $compensationError) {
                $this->setError(Text::_('COM_DHARMASHOP_FIELD_RELATIONS_FAILED')); return false;
            }
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_SAVE_FAILED')); return false;
        }
        $this->cleanCache();
        return true;
    }
    protected function canDelete($record): bool { return (int) $record->state === -2 && parent::canDelete($record); }
    protected function getReorderConditions($table): array { return ['target = ' . $this->getDatabase()->quote((string) $table->target)]; }
}
