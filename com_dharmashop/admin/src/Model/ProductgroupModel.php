<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Dharma\Component\DharmaShop\Administrator\Catalog\VariantRepository;
use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Dharma\Component\DharmaShop\Administrator\Pricing\PriceResolver;
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

    public function getTable($name = 'Productgroup', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.productgroup', 'productgroup', ['control' => 'jform', 'load_data' => $loadData]);
        if (!$form) { return false; }
        $id = (int) ($this->getState('productgroup.id') ?: (((array) $data)['id'] ?? 0));
        if ($id > 0) {
            $xml = new \SimpleXMLElement('<form><fieldset name="executions"><field name="executions" type="subform" validate="Subform" multiple="true" layout="joomla.form.field.subform.repeatable" label="COM_DHARMASHOP_GROUP_EXECUTIONS" max="100"><form><fieldset name="row"><field name="title" type="text" label="JGLOBAL_TITLE" filter="string" maxlength="255"/><field name="sku" type="text" label="COM_DHARMASHOP_FIELD_SKU_LABEL" maxlength="100"/><field name="price" type="number" label="COM_DHARMASHOP_FIELD_PRICE_LABEL" min="0" step="0.01"/><field name="stock_qty" type="number" label="COM_DHARMASHOP_FIELD_STOCK_QTY_LABEL" min="0" step="0.001"/></fieldset></form></field></fieldset></form>');
            $rows = $xml->fieldset->field->form->fieldset;
            foreach ((new GroupService($this->getDatabase()))->axes($id) as $axis) {
                $field = $rows->addChild('field');
                $field['name'] = 'axis_' . $axis->id;
                $field['type'] = 'list';
                $field['label'] = $axis->title;
                $field['filter'] = 'int';
                $field->addChild('option', 'JOPTION_SELECT')->addAttribute('value', '');
                $options = $this->getDatabase()->setQuery('SELECT id,title FROM #__dharmashop_field_options WHERE field_id=' . (int) $axis->id . ' ORDER BY ordering,id')->loadObjectList();
                foreach ($options as $option) {
                    $element = $field->addChild('option');
                    $element[0] = $option->title;
                    $element['value'] = $option->id;
                }
            }
            $form->load($xml, false);
            // Static loadForm binding precedes these dynamically added fields.
            $values = $loadData ? (array) $this->loadFormData() : (array) $data;
            $form->bind(['executions' => $values['executions'] ?? []]);
        }
        if (!$this->getCurrentUser()->authorise('core.edit.state', 'com_dharmashop')) {
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }
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
            }
        }
        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);
        if (!$item) { return $item; }
        $service = new GroupService($this->getDatabase());
        $item->axes = $item->members = $item->gallery = [];
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
        try {
            $axes = $service->validateAxes((array) ($data['axes'] ?? []));
            $members = array_map(static fn($row) => (int) (((array) $row)['product_id'] ?? 0), (array) ($data['members'] ?? []));
            $media = ImageUploader::filterGallery($data['gallery'] ?? []);
            $revision = (int) ($data['revision'] ?? 0);
            unset($data['display_mode'], $data['axes'], $data['members'], $data['gallery'], $data['executions'], $data['revision'], $data['generation_catid']);
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
            $db->transactionCommit();
            Factory::getApplication()->setUserState('com_dharmashop.group.selection', null);
            return true;
        } catch (\Throwable $error) {
            if ($started) { $db->transactionRollback(); }
            $this->setState('productgroup.id', $originalId);
            $this->setError(Text::_($error->getMessage()));
            return false;
        }
    }

    /** Validate all rows first. No outer transaction around ProductTable's asset writes. */
    public function createExecutions(int $id, array $data): array
    {
        $db = $this->getDatabase();
        $groups = new GroupService($db);
        $group = $groups->get($id);
        if (!$group) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_NOT_FOUND'); }
        if ((int)($data['revision'] ?? 0) !== (int)$group->revision) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_CHANGED'); }
        $user = $this->getCurrentUser();
        $catid = (int) ($data['generation_catid'] ?? 0);
        if (!$catid || !$user->authorise('core.create', 'com_dharmashop.category.' . $catid)
            || !$user->authorise('core.edit', 'com_dharmashop')) {
            throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
        }
        if (!$db->setQuery("SELECT id FROM #__categories WHERE id=" . $catid . " AND extension='com_dharmashop'")->loadResult()) {
            throw new \RuntimeException('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED');
        }
        $axes = $groups->axes($id);
        $plan = $hashes = $skus = [];
        $repository = new VariantRepository($db, new PriceResolver());
        foreach ((array) ($data['executions'] ?? []) as $key => $row) {
            $row = (array) $row;
            if (!array_filter($row, static fn($value) => $value !== '' && $value !== null && $value !== 0 && $value !== '0')) { continue; }
            $title = trim((string) ($row['title'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $skuKey = mb_strtolower($sku);
            if ($title === '' || $sku === '' || mb_strlen($title) > 255 || mb_strlen($sku) > 100 || isset($skus[$skuKey])) {
                throw new \RuntimeException('COM_DHARMASHOP_GROUP_GENERATION_REQUIRED');
            }
            $skus[$skuKey] = true;
            $values = $fields = $markers = [];
            foreach ($axes as $axis) {
                $values[(int) $axis->id] = (int) ($row['axis_' . $axis->id] ?? 0);
                $fields['f_' . $axis->id] = $values[(int) $axis->id];
                $markers['f_' . $axis->id] = 1;
            }
            $hash = GroupService::hash($values);
            if (isset($hashes[$hash])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_DUPLICATE'); }
            $hashes[$hash] = true;
            $product = [
                'id' => 0, 'catid' => $catid, 'title' => $title, 'alias' => '', 'state' => 0, 'sku' => $sku,
                'price' => $row['price'] ?? '', 'stock_qty' => $row['stock_qty'] ?? 0, 'stock_tracked' => 1,
                'group_id' => $id, 'inherit_intro' => 'group', 'inherit_full' => 'group', 'inherit_images' => 'group',
                'custom_fields' => $fields, 'custom_fields_present' => $markers,
            ];
            $prepared = (new FieldValues($db))->prepare('product', $product);
            $groups->validateProduct(0, $id, $prepared);
            $repository->assertSkuAvailable(0, $sku);
            foreach (['price', 'stock_qty'] as $number) {
                if ($product[$number] !== '' && (!is_numeric($product[$number]) || (float) $product[$number] < 0)) {
                    throw new \RuntimeException('COM_DHARMASHOP_GROUP_GENERATION_REQUIRED');
                }
            }
            $plan[$key] = $product;
        }
        if (!$plan || count($plan) > 100 || count($groups->members($id)) + count($plan) > GroupService::MEMBER_LIMIT) {
            throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT');
        }
        $created = [];
        $remaining = (array) ($data['executions'] ?? []);
        foreach ($plan as $key => $product) {
            $model = Factory::getApplication()->bootComponent('com_dharmashop')->getMVCFactory()->createModel('Product', 'Administrator', ['ignore_request' => true]);
            try {
                if (!$model->save($product)) { return ['created' => $created, 'remaining' => $remaining, 'error' => (string) $model->getError()]; }
            } catch (\Throwable $error) {
                return ['created' => $created, 'remaining' => $remaining, 'error' => Text::_($error->getMessage())];
            }
            $created[] = (int) $model->getState('product.id');
            unset($remaining[$key]);
        }
        return ['created' => $created, 'remaining' => [], 'error' => ''];
    }

    protected function canDelete($record): bool
    {
        return (int) $record->state === -2 && parent::canDelete($record);
    }
}
