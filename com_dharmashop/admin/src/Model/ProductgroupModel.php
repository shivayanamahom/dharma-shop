<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Catalog\ProductPicker;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupTaxonomy;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupGenerator;
use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

\defined('_JEXEC') or die;

/** Native group form; creating a sellable product always goes through ProductModel. */
class ProductgroupModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.productgroup';

    protected $batch_copymove = '';
    protected $batch_commands = [
        'category' => 'batchCategory',
        'extra_categories' => 'batchExtraCategories',
        'brand' => 'batchBrand',
        'badges' => 'batchBadges',
    ];

    public function getTable($name = 'Productgroup', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.productgroup', 'productgroup', ['control' => 'jform', 'load_data' => $loadData]);
        if (!$form) { return false; }
        $id = (int) ($this->getState('productgroup.id') ?: (((array) $data)['id'] ?? 0));
        $values = $loadData ? (array) $this->loadFormData() : (array) $data;
        $ids = (array) ($values['axes'] ?? []);
        $generator = new GroupGenerator($this->getDatabase());
        if ($ids) {
            $axes = $generator->axes($ids);
            $xml = new \SimpleXMLElement('<form><fields name="generation_values"><fieldset name="generation_values"/></fields><fieldset name="executions"><field name="executions" type="subform" validate="Subform" multiple="true" min="0" max="100" component="com_dharmashop" client="administrator" layout="field.generator" label="COM_DHARMASHOP_GROUP_EXECUTIONS"><form><fieldset name="row"><field name="title_preview" type="text" label="COM_DHARMASHOP_GENERATOR_PREVIEW_TITLE" readonly="true" filter="unset" class="fw-semibold"/><field name="custom_title" type="text" label="COM_DHARMASHOP_GENERATOR_TITLE_OVERRIDE" description="COM_DHARMASHOP_GENERATOR_TITLE_OVERRIDE_DESC" filter="string" maxlength="255"/><field name="sku" type="text" label="COM_DHARMASHOP_FIELD_SKU_LABEL" description="COM_DHARMASHOP_GENERATOR_SKU_DESC" maxlength="100"/><field name="price" type="number" label="COM_DHARMASHOP_FIELD_PRICE_LABEL" min="0" step="0.01" default="0"/><field name="stock_qty" type="number" label="COM_DHARMASHOP_FIELD_STOCK_QTY_LABEL" min="0" step="0.001" default="0"/><field name="product_state" type="list" label="JSTATUS" default="1" validate="options"><option value="0">JUNPUBLISHED</option><option value="1">JPUBLISHED</option></field><field name="image" type="media" label="COM_DHARMASHOP_GENERATOR_IMAGE" description="COM_DHARMASHOP_GENERATOR_IMAGE_DESC" directory="dharmashop" types="images" filter="Dharma\Component\DharmaShop\Administrator\Image\ImageUploader::filterPath"/></fieldset></form></field></fieldset></form>');
            $rowFields = $xml->fieldset->field->form->fieldset;
            if (!$this->getCurrentUser()->authorise('core.edit.state', 'com_dharmashop')) {
                $stateFields = $xml->xpath('//field[@name="product_state"]');
                $stateFields[0]['default'] = '0';
            }
            foreach ($axes as $axis) {
                foreach ([$rowFields, $xml->fields->fieldset] as $index => $parent) {
                    $field = $parent->addChild('field');
                    $pool = $index === 1;
                    $field['name'] = ($pool ? 'f_' : 'axis_') . $axis->id;
                    $field['type'] = 'list';
                    $field['label'] = $axis->title;
                    $field['filter'] = $pool ? 'int_array' : 'int';
                    $field['validate'] = 'options';
                    if ($pool) {
                        $field['multiple'] = 'true';
                        $field['layout'] = 'joomla.form.field.list-fancy-select';
                    } else { $field->addChild('option', 'JSELECT')->addAttribute('value', ''); }
                    foreach ($axis->options as $option => $title) {
                        $element = $field->addChild('option');
                        $element[0] = $title;
                        $element['value'] = $option;
                    }
                }
            }
            $form->load($xml, false);
            $form->bind(['executions' => $values['executions'] ?? [], 'generation_values' => $values['generation_values'] ?? []]);
        }
        if (!$this->getCurrentUser()->authorise('core.edit.state', 'com_dharmashop')) {
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('state', 'filter', 'unset');
            $form->setFieldAttribute('generation_state', 'default', '0');
            $form->setValue('generation_state', null, 0);
        }
        (new FieldValues($this->getDatabase(), 'group', array_map('intval', $ids)))->addToForm($form, 'product', $values, true);
        // Preload once before Joomla creates an independent Form for each repeated row.
        (new ProductPicker($this->getDatabase()))->preload($form->getData()->toArray(), 0, true);
        return $form;
    }

    protected function loadFormData(): mixed
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_dharmashop.edit.productgroup.data', []);
        if (!$data) {
            $data = $this->getItem();
            if ($data && !$data->id) {
                $ids = (array) $app->getUserState('com_dharmashop.group.selection', []);
                $data->members = array_map(static fn($id) => ['product_id' => (int) $id], $ids);
                $data->catid = $ids ? (int) $this->getDatabase()->setQuery('SELECT catid FROM #__dharmashop_products WHERE id=' . (int) reset($ids))->loadResult() : '';
            }
        }
        $values = (array) $data;
        $draft = $app->getUserState('com_dharmashop.generator.' . (int) ($values['id'] ?? 0), []);
        if ($draft && !array_key_exists('executions', $values)) {
            $data = (object) array_replace($values, $draft);
        }
        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);
        if (!$item) { return $item; }
        $service = new GroupService($this->getDatabase());
        $item->axes = $item->members = $item->gallery = [];
        foreach ((new GroupTaxonomy($this->getDatabase()))->get((int) $item->id) as $name => $value) { $item->$name = $value; }
        if ($item->id) {
            $item->axes = array_map(static fn($axis) => (int) $axis->id, $service->axes((int) $item->id));
            $item->members = array_map(static fn($product) => ['product_id' => (int) $product->id], $service->members((int) $item->id));
            $item->gallery = $service->media((int) $item->id);
        }
        return $item;
    }

    public function save($data): bool
    {
        $db = $this->getDatabase();
        $service = new GroupService($db);
        $started = false;
        $originalId = (int) ($data['id'] ?? 0);
        $draft = array_intersect_key($data, array_flip(['executions', 'generation_values', 'generation_catid', 'generation_price', 'generation_stock', 'generation_state']));
        try {
            $taxonomyService = new GroupTaxonomy($db);
            $taxonomy = $taxonomyService->prepare(array_replace($taxonomyService->get($originalId), $data));
            $data['catid'] = $taxonomy['catid'];
            $data['brand_id'] = $taxonomy['brand_id'] ?: null;
            $axes = $service->validateAxes((array) ($data['axes'] ?? []));
            $fieldValues = new FieldValues($db, 'group', $axes);
            $common = $fieldValues->prepare('product', $data);
            $generator = new GroupGenerator($db);
            $generator->title((string) ($data['title_template'] ?? ''), (string) ($data['title'] ?? ''), $generator->axes($axes), []);
            $members = array_map(static fn($row) => (int) (((array) $row)['product_id'] ?? 0), (array) ($data['members'] ?? []));
            $media = ImageUploader::filterGallery($data['gallery'] ?? []);
            $revision = (int) ($data['revision'] ?? 0);
            unset($data['extra_catids'], $data['badge_ids'], $data['display_mode'], $data['axes'], $data['members'], $data['gallery'], $data['executions'], $data['revision'], $data['generation_catid'], $data['generation_values'], $data['generation_price'], $data['generation_stock'], $data['generation_state'], $data['custom_fields'], $data['custom_fields_present']);
            // This table has no asset_id: its Table::store does not take Joomla asset table locks.
            $db->transactionStart();
            $started = true;
            if ($originalId > 0) {
                $current = (int) $db->setQuery('SELECT revision FROM #__dharmashop_product_groups WHERE id=' . $originalId . ' FOR UPDATE')->loadResult();
                if (!$current || $current !== $revision) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_CHANGED'); }
            }
            if (!parent::save($data)) { $db->transactionRollback(); return false; }
            $id = (int) $this->getState('productgroup.id');
            $service->configure($id, $axes, $members, $media);
            $taxonomyService->store($id, $taxonomy);
            $fieldValues->save('product', $id, $common);
            // Название, состояние и привязки группы входят в индекс выдачи (C89): пересобираем после записи таксономии.
            $service->rebuild($id);
            $db->transactionCommit();
            Factory::getApplication()->setUserState('com_dharmashop.group.selection', null);
            if (array_key_exists('executions', $draft)) { Factory::getApplication()->setUserState('com_dharmashop.generator.' . $id, $draft); }
            return true;
        } catch (\Throwable $error) {
            if ($started) { $db->transactionRollback(); }
            $this->setState('productgroup.id', $originalId);
            $this->setError(Text::_($error->getMessage()));
            return false;
        }
    }

    public function prepareGeneration(int $id, array $data): array
    {
        return (new GroupGenerator($this->getDatabase()))->prepare($id, $data);
    }

    /** Validate all rows first. No outer transaction around ProductTable's asset writes. */
    public function createExecutions(int $id, array $data): array
    {
        $draft = (new GroupGenerator($this->getDatabase()))->plan($id, $data);
        $created = [];
        $remaining = (array) ($data['executions'] ?? []);
        foreach ($draft['skipped'] as $key) { unset($remaining[$key]); }
        foreach ($draft['products'] as $key => $product) {
            $model = Factory::getApplication()->bootComponent('com_dharmashop')->getMVCFactory()->createModel('Product', 'Administrator', ['ignore_request' => true]);
            try {
                if (!$model->save($product)) { throw new \RuntimeException((string) $model->getError()); }
            } catch (\Throwable $error) {
                Factory::getApplication()->setUserState('com_dharmashop.generator.' . $id, ['executions' => $remaining]);
                return ['created' => $created, 'remaining' => $remaining, 'skipped' => count($draft['skipped']), 'error' => Text::_($error->getMessage())];
            }
            $created[] = (int) $model->getState('product.id');
            unset($remaining[$key]);
        }
        Factory::getApplication()->setUserState('com_dharmashop.generator.' . $id, null);
        return ['created' => $created, 'remaining' => [], 'skipped' => count($draft['skipped']), 'error' => ''];
    }

    protected function canDelete($record): bool
    {
        return (int) $record->state === -2 && parent::canDelete($record);
    }

    /** Keep each optional block independent; the native AdminModel dispatcher handles authorization and selection. */
    /** Публикация и снятие групп меняют их место в индексе выдачи. */
    public function publish(&$pks, $value = 1)
    {
        $result = parent::publish($pks, $value);

        if ($result) {
            (new GroupService($this->getDatabase()))->rebuildMany(array_map('intval', (array) $pks));
        }

        return $result;
    }

    public function batch($commands, $pks, $contexts)
    {
        foreach (array_keys($this->batch_commands) as $block) {
            $value = (array) ($commands[$block] ?? []);
            $action = (string) ($value['action'] ?? 'skip');
            $ids = $value['value'] ?? '';
            $allowsEmptySelection = in_array($action, ['clear', 'replace'], true);
            if ($action === 'skip' || (!$allowsEmptySelection && (is_array($ids) ? !array_filter($ids) : trim((string) $ids) === ''))) {
                unset($commands[$block]);
                continue;
            }
            $commands[$block] = ['action' => $action, 'value' => $ids];
        }
        return parent::batch($commands, $pks, $contexts);
    }

    private function batchGroups(array $pks, callable $change): bool
    {
        $db = $this->getDatabase();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $taxonomy = new GroupTaxonomy($db);
        foreach (array_values(array_unique(array_filter(array_map('intval', $pks)))) as $id) {
            $row = $db->setQuery('SELECT title,catid FROM #__dharmashop_product_groups WHERE id=' . $id)->loadObject();
            if (!$row) { continue; }
            if (!$user->authorise('core.edit', 'com_dharmashop')) {
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_ENTRY_SKIP_NO_ACCESS', $row->title), 'warning');
                continue;
            }
            $started = false;
            try {
                $db->transactionStart();
                $started = true;
                $revision = (int) $db->setQuery('SELECT revision FROM #__dharmashop_product_groups WHERE id=' . $id . ' FOR UPDATE')->loadResult();
                if (!$revision) { $db->transactionRollback(); $started = false; continue; }
                $current = $taxonomy->get($id);
                $next = $change($id, $current);
                if ($next === null) { $db->transactionCommit(); $started = false; continue; }
                $prepared = $taxonomy->prepare($next);
                if ((int) $prepared['catid'] !== (int) $current['catid'] && !$user->authorise('core.create', 'com_dharmashop.category.' . (int) $prepared['catid'])) {
                    throw new \RuntimeException('COM_DHARMASHOP_BATCH_ERROR_CREATE_DENIED');
                }
                $record = (object) ['id' => $id, 'catid' => $prepared['catid'], 'brand_id' => $prepared['brand_id'] ?: null, 'revision' => $revision + 1];
                $db->updateObject('#__dharmashop_product_groups', $record, 'id', true);
                $taxonomy->store($id, $prepared);
                (new GroupService($db))->rebuild($id);
                $db->transactionCommit();
                $started = false;
            } catch (\Throwable $error) {
                if ($started) { $db->transactionRollback(); }
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_ENTRY_SKIP', $row->title, Text::_($error->getMessage())), 'warning');
            }
        }
        return true;
    }

    protected function batchCategory($value, $pks, $contexts): bool
    {
        if (!in_array($value['action'] ?? '', ['set'], true)) { $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE')); return false; }
        $target = (int) ($value['value'] ?? 0);
        return $this->batchGroups((array) $pks, static function ($id, array $taxonomy) use ($target): ?array {
            if ($taxonomy['catid'] === $target) { return null; }
            $taxonomy['catid'] = $target;
            return $taxonomy;
        });
    }

    protected function batchExtraCategories($value, $pks, $contexts): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($value['value'] ?? [])))));
        $action = (string) ($value['action'] ?? '');
        if (!in_array($action, ['add', 'remove', 'replace'], true)) { $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE')); return false; }
        return $this->batchGroups((array) $pks, static function ($id, array $taxonomy) use ($ids, $action): ?array {
            $old = $taxonomy['extra_catids'];
            $taxonomy['extra_catids'] = match ($action) {
                'add' => array_values(array_unique(array_merge($old, $ids))),
                'remove' => array_values(array_diff($old, $ids)),
                'replace' => $ids,
                default => $old,
            };
            return $taxonomy['extra_catids'] === $old ? null : $taxonomy;
        });
    }

    protected function batchBrand($value, $pks, $contexts): bool
    {
        $action = (string) ($value['action'] ?? '');
        if (!in_array($action, ['set', 'clear'], true)) { $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE')); return false; }
        $brand = $action === 'clear' ? 0 : (int) ($value['value'] ?? 0);
        return $this->batchGroups((array) $pks, static function ($id, array $taxonomy) use ($brand): ?array {
            if ($taxonomy['brand_id'] === $brand) { return null; }
            $taxonomy['brand_id'] = $brand;
            return $taxonomy;
        });
    }

    protected function batchBadges($value, $pks, $contexts): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($value['value'] ?? [])))));
        $action = (string) ($value['action'] ?? '');
        if (!in_array($action, ['add', 'remove', 'replace'], true)) { $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_VALUE')); return false; }
        return $this->batchGroups((array) $pks, static function ($id, array $taxonomy) use ($ids, $action): ?array {
            $old = $taxonomy['badge_ids'];
            $taxonomy['badge_ids'] = match ($action) {
                'add' => array_values(array_unique(array_merge($old, $ids))),
                'remove' => array_values(array_diff($old, $ids)),
                'replace' => $ids,
                default => $old,
            };
            return $taxonomy['badge_ids'] === $old ? null : $taxonomy;
        });
    }
}
