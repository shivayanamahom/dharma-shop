<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;

use Dharma\Component\DharmaShop\Administrator\Support\FieldGroupTabs;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Entity-independent form/value orchestration. Value providers do not execute SQL. */
final class FieldValues
{
    private array $definitionCache = [];
    /** Definition target stays product; group is a separate owner of generator defaults. */
    public function __construct(private DatabaseInterface $db, private string $owner = '', private array $excluded = []) {}

    /** Product scope includes ancestors of primary and additional categories; category scope is the record itself. */
    private function scope(string $target, array $data): array
    {
        $this->tables($target);
        if ($target === 'category') { return [(int) ($data['id'] ?? 0)]; }
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge(
            [(int) ($data['catid'] ?? 0)], (array) ($data['extra_catids'] ?? [])
        )), static fn (int $id): bool => $id > 1)));
        if (!$ids) { return [0]; }
        $query = $this->db->createQuery()->select('DISTINCT a.id')->from($this->db->quoteName('#__categories', 'c'))
            ->join('INNER', $this->db->quoteName('#__categories', 'a') . ' ON a.lft <= c.lft AND a.rgt >= c.rgt')
            ->where('c.id IN (' . implode(',', $ids) . ')')
            ->where('c.extension = ' . $this->db->quote('com_dharmashop'))
            ->where('a.extension = ' . $this->db->quote('com_dharmashop'));
        return array_map('intval', $this->db->setQuery($query)->loadColumn()) ?: [0];
    }

    /** Only published, accessible definitions from the selected target and enabled runtime providers. */
    private function definitions(string $target, array $data): array
    {
        $key = serialize([$target, $data['catid'] ?? 0, $data['extra_catids'] ?? [], $target === 'category' ? ($data['id'] ?? 0) : 0]);
        if (isset($this->definitionCache[$key])) { return $this->definitionCache[$key]; }
        $scope = $this->scope($target, $data);
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0];
        $query = $this->db->createQuery()->select($this->db->quoteName([
            'f.id', 'f.group_id', 'f.title', 'f.type', 'f.store', 'f.required', 'f.unit', 'f.params',
        ]))->from($this->db->quoteName('#__dharmashop_fields', 'f'))
            ->join('LEFT', $this->db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = f.group_id')
            ->where('f.target = ' . $this->db->quote($target))->where('f.state = 1')->where('f.level = 1')
            ->where('f.access IN (' . implode(',', $levels) . ')')
            ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))')
            ->where('(f.all_categories = 1 OR EXISTS (SELECT 1 FROM '
                . $this->db->quoteName('#__dharmashop_category_fields', 'cf')
                . ' WHERE cf.field_id = f.id AND cf.catid IN (' . implode(',', $scope) . ')))')
            ->order('COALESCE(g.ordering, 0), f.group_id, f.ordering, f.id');
        $providers = TypeRegistry::current()->all();
        $fields = [];
        foreach ($this->db->setQuery($query)->loadObjectList() as $field) {
            if (in_array((int) $field->id, $this->excluded, true)) { continue; }
            $provider = $providers[$field->type] ?? null;
            if (!$provider instanceof ValueTypeInterface || $provider->store() !== $field->store) { continue; }
            $field->settings = (new Registry($field->params))->toArray();
            $field->provider = $provider;
            if ($this->owner === 'group') { $field->required = 0; }
            $field->options = [];
            $fields[(int) $field->id] = $field;
        }
        if ($fields) {
            $query = $this->db->createQuery()->select($this->db->quoteName(['id', 'field_id', 'title', 'alias', 'image', 'color']))
                ->from($this->db->quoteName('#__dharmashop_field_options'))
                ->where('field_id IN (' . implode(',', array_keys($fields)) . ')')->order('ordering, id');
            foreach ($this->db->setQuery($query)->loadObjectList() as $option) { $fields[(int) $option->field_id]->options[(int) $option->id] = $option; }
        }
        return $this->definitionCache[$key] = $fields;
    }

    /** Add native controls to the existing group fieldsets and bind persisted/submitted values. */
    public function addToForm(Form $form, string $target, mixed $data, bool $editing): void
    {
        $data = $data instanceof Registry ? $data->toArray() : (array) $data;
        $fields = $this->definitions($target, $data);
        // Form::setField does not create missing groups/fieldsets. Declare both paths first.
        $skeleton = new \SimpleXMLElement('<form />');
        foreach (['custom_fields', 'custom_fields_present'] as $groupName) {
            $group = $skeleton->addChild('fields');
            $group['name'] = $groupName;
            foreach (array_unique(array_map(static fn (object $field): int => (int) $field->group_id, $fields)) as $groupId) {
                $group->addChild('fieldset')->addAttribute('name', FieldGroupTabs::fieldset($groupId));
            }
        }
        $form->load($skeleton, false);
        $id = (int) ($data['id'] ?? 0);
        [$rows, $texts] = $id > 0 && $editing ? $this->read($target, $id, array_keys($fields)) : [[], []];
        $posted = (array) ($data['custom_fields'] ?? []);
        $present = (array) ($data['custom_fields_present'] ?? []);
        foreach ($fields as $fieldId => $field) {
            $key = 'f_' . $fieldId;
            $fieldset = FieldGroupTabs::fieldset((int) $field->group_id);
            $xml = $field->provider->input($field, $field->options);
            if ($this->owner === 'group') { $xml['owner'] = 'group'; }
            if (!$editing && !isset($present[$key])) { $xml['required'] = 'false'; }
            $form->setField($xml, 'custom_fields', true, $fieldset);
            $marker = new \SimpleXMLElement('<field type="hidden" filter="int" />');
            $marker['name'] = $key;
            $form->setField($marker, 'custom_fields_present', true, $fieldset);
            $form->setValue($key, 'custom_fields_present', 1);
            $value = array_key_exists($key, $posted) ? $posted[$key]
                : (isset($present[$key]) ? '' : ($id > 0
                    ? $field->provider->decode($field, $rows[$fieldId] ?? [], $texts[$fieldId] ?? null)
                    : $field->provider->defaultValue($field, $field->options)));
            if ($value === '' && $field->store === 'range') { $value = ['from' => '', 'to' => '']; }
            $form->setValue($key, 'custom_fields', $value);
        }
    }

    /** Prevalidate before Joomla Table::store. Absent form keys preserve existing values. */
    public function prepare(string $target, array $data): array
    {
        if (!array_key_exists('custom_fields', $data) && !array_key_exists('custom_fields_present', $data)) { return []; }
        $posted = (array) ($data['custom_fields'] ?? []);
        $present = (array) ($data['custom_fields_present'] ?? []);
        $result = [];
        foreach ($this->definitions($target, $data) as $fieldId => $field) {
            $key = 'f_' . $fieldId;
            if (!array_key_exists($key, $posted) && !isset($present[$key])) { continue; }
            try {
                $value = $posted[$key] ?? ($field->store === 'range' ? ['from' => '', 'to' => ''] : '');
                $result[$fieldId] = $field->provider->encode($field, $field->options, $value);
            } catch (\InvalidArgumentException $error) {
                throw new \InvalidArgumentException(Text::sprintf('COM_DHARMASHOP_FIELD_VALUE_ERROR', $field->title, Text::_($error->getMessage())), 0, $error);
            }
        }
        // No writes to definitions outside the current scope; their saved values remain intact.
        return $result;
    }

    /** Decode only saved defaults in this scope. Zero/false are values, empty containers are not. */
    public function values(string $target, array $data): array
    {
        $fields = $this->definitions($target, $data);
        [$rows, $texts] = $this->read($target, (int) ($data['id'] ?? 0), array_keys($fields));
        $values = [];
        foreach ($fields as $id => $field) {
            if (!isset($rows[$id]) && !array_key_exists($id, $texts)) { continue; }
            $value = $field->provider->decode($field, $rows[$id] ?? [], $texts[$id] ?? null);
            if (self::hasValue($value)) { $values['f_' . $id] = $value; }
        }
        return $values;
    }

    /** Fill and validate every applicable field before a generator writes its first product. */
    public function forCreation(array $data, array $common): array
    {
        $fields = $this->definitions('product', $data);
        $values = [];
        $posted = (array) ($data['custom_fields'] ?? []);
        foreach ($fields as $id => $field) {
            $key = 'f_' . $id;
            $value = $posted[$key] ?? $common[$key] ?? $field->provider->defaultValue($field, $field->options);
            try { $field->provider->encode($field, $field->options, $value); }
            catch (\InvalidArgumentException $error) {
                throw new \InvalidArgumentException(Text::sprintf('COM_DHARMASHOP_FIELD_VALUE_ERROR', $field->title, Text::_($error->getMessage())), 0, $error);
            }
            // Required empty values have just been rejected; do not write empty optional values.
            if (self::hasValue($value) || array_key_exists($key, $posted)) { $values[$key] = $value; }
        }
        $data['custom_fields'] = $values;
        $data['custom_fields_present'] = array_fill_keys(array_keys($values), 1);
        return $data;
    }

    private static function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $part) { if (self::hasValue($part)) { return true; } }
            return false;
        }
        return $value !== null && $value !== '';
    }

    private function read(string $target, int $id, array $fieldIds): array
    {
        if (!$fieldIds) { return [[], []]; }
        [$simple, $long, $column] = $this->tables($target);
        $base = fn (string $table) => $this->db->createQuery()->from($this->db->quoteName($table))
            ->where($column . ' = ' . $id)->where('field_id IN (' . implode(',', $fieldIds) . ')');
        $query = $base($simple)->select($this->db->quoteName(['field_id', 'option_id', 'num', 'num_end', 'str']))->order('id');
        if ($this->owner !== 'group' && $target === 'product') { $query->where('variant_id IS NULL'); }
        $rows = [];
        foreach ($this->db->setQuery($query)->loadObjectList() as $row) { $rows[(int) $row->field_id][] = $row; }
        $query = $base($long)->select($this->db->quoteName(['field_id', 'value']));
        if ($this->owner !== 'group' && $target === 'product') { $query->where('variant_id IS NULL'); }
        $texts = $this->db->setQuery($query)->loadAssocList('field_id', 'value');
        $media = $this->mediaTable($target);
        $query = $base($media)->select($this->db->quoteName(['field_id','path','alt','title','filename','bytes','mime']))->order('ordering, id');
        if ($this->owner !== 'group' && $target === 'product') { $query->where('variant_id IS NULL'); }
        foreach ($this->db->setQuery($query)->loadObjectList() as $row) { $rows[(int) $row->field_id][] = $row; }
        return [$rows, $texts];
    }

    /** Caller owns the relation transaction, after Table::store has finished. */
    public function save(string $target, int $id, array $prepared): array
    {
        if ($id <= 0) { throw new \InvalidArgumentException('Invalid field owner.'); }
        [$simple, $long, $column] = $this->tables($target);
        $removed = [];
        $media = $this->mediaTable($target);
        foreach ($prepared as $fieldId => $value) {
            if (array_key_exists('media', $value)) {
                $query = $this->db->createQuery()->from($this->db->quoteName($media))
                    ->where($column . ' = ' . $id)->where('field_id = ' . (int) $fieldId);
                if ($this->owner !== 'group' && $target === 'product') { $query->where('variant_id IS NULL'); }
                $old = $this->db->setQuery((clone $query)->select('path'))->loadColumn();
                $this->db->setQuery((clone $query)->delete())->execute();
                foreach ($value['media'] as $values) {
                    $row = (object) ([$column=>$id, 'field_id'=>(int) $fieldId] + $values);
                    $this->db->insertObject($media, $row);
                }
                $removed = array_merge($removed, array_diff($old, array_column($value['media'], 'path')));
                continue;
            }
            foreach ([$simple, $long] as $table) {
                $query = $this->db->createQuery()->delete($this->db->quoteName($table))
                    ->where($column . ' = ' . $id)->where('field_id = ' . (int) $fieldId);
                if ($this->owner !== 'group' && $target === 'product') { $query->where('variant_id IS NULL'); }
                $this->db->setQuery($query)->execute();
            }
            foreach ($value['rows'] as $values) {
                $row = (object) ([$column => $id, 'field_id' => (int) $fieldId] + $values);
                $this->db->insertObject($simple, $row);
            }
            if ($value['text'] !== null) {
                $row = (object) [$column => $id, 'field_id' => (int) $fieldId, 'value' => $value['text']];
                $this->db->insertObject($long, $row);
            }
        }
        return $removed;
    }

    public function deleteEntity(string $target, int $id): array
    {
        if ($id <= 0) { return []; }
        [$simple, $long, $column] = $this->tables($target);
        $media = $this->mediaTable($target);
        $query = $this->db->createQuery()->from($this->db->quoteName($media))->where($column . ' = ' . $id);
        // Reserved main gallery is owned by GalleryRepository, not by custom fields.
        if ($target === 'product') { $query->where('field_id > 0'); }
        $removed = $this->db->setQuery((clone $query)->select('path'))->loadColumn();
        $this->db->setQuery((clone $query)->delete())->execute();
        foreach ([$simple, $long] as $table) {
            $this->db->setQuery($this->db->createQuery()->delete($this->db->quoteName($table))->where($column . ' = ' . $id))->execute();
        }
        return $removed;
    }

    private function tables(string $target): array
    {
        if ($this->owner === 'group' && $target === 'product') {
            return ['#__dharmashop_group_field_values', '#__dharmashop_group_field_texts', 'group_id'];
        }
        return match ($target) {
            'product' => ['#__dharmashop_field_values', '#__dharmashop_field_texts', 'product_id'],
            'category' => ['#__dharmashop_category_field_values', '#__dharmashop_category_field_texts', 'category_id'],
            default => throw new \InvalidArgumentException('Invalid field target.'),
        };
    }

    private function mediaTable(string $target): string
    {
        return $this->owner === 'group' ? '#__dharmashop_group_field_media'
            : ($target === 'product' ? '#__dharmashop_field_media' : '#__dharmashop_category_field_media');
    }
}
