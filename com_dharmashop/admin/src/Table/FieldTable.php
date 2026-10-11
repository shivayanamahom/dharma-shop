<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Table;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Dharma\Component\DharmaShop\Administrator\Fields\DisplaySettings;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class FieldTable extends Table
{
    protected $_supportNullValue = true;
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_fields', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }
    public function bind($src, $ignore = ''): bool
    {
        $src = (array) $src;
        if (isset($src['params']) && is_array($src['params'])) { $src['params'] = (string) new Registry($src['params']); }
        return parent::bind($src, $ignore);
    }
    public function check(): bool
    {
        $db = $this->getDatabase();
        $id = (int) $this->id;
        $this->title = trim(strip_tags((string) $this->title));
        $alias = trim((string) $this->alias);
        $this->alias = ApplicationHelper::stringURLSafe($alias === '' ? $this->title : $alias);
        if ($this->title === '' || $this->alias === '' || mb_strlen($this->title) > 255 || mb_strlen($this->alias) > 190) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_TEXT_INVALID')); return false;
        }
        if (!in_array((string) $this->target, ['product', 'category'], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_TARGET_INVALID')); return false;
        }
        if ($id > 0) {
            $query = $db->createQuery()->select($db->quoteName(['target', 'type']))->from($db->quoteName('#__dharmashop_fields'))
                ->where('id = :id')->bind(':id', $id, ParameterType::INTEGER);
            $previous = $db->setQuery($query)->loadObject();
            if ($previous && ((string) $previous->target !== (string) $this->target
                || (string) $previous->type !== (string) $this->type)) {
                $this->setError(Text::_('COM_DHARMASHOP_FIELD_IDENTITY_LOCKED')); return false;
            }
        }
        $group = (int) $this->group_id;
        if ($group > 0) {
            $query = $db->createQuery()->select($db->quoteName(['target', 'state']))->from($db->quoteName('#__dharmashop_fieldgroups'))
                ->where('id = :group')->bind(':group', $group, ParameterType::INTEGER);
            $record = $db->setQuery($query)->loadObject();
            if (!$record || $record->target !== $this->target || (int) $record->state === -2) {
                $this->setError(Text::_('COM_DHARMASHOP_FIELD_GROUP_INVALID')); return false;
            }
        }
        $alias = (string) $this->alias;
        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_fields'))
            ->where('alias = :alias')->where('id <> :id')->bind(':alias', $alias)->bind(':id', $id, ParameterType::INTEGER);
        if ($db->setQuery($query, 0, 1)->loadResult()) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_ALIAS_EXISTS')); return false;
        }
        try {
            if (!isset(TypeRegistry::current()->all()[(string) $this->type])) {
                $this->setError(Text::_('COM_DHARMASHOP_FIELD_PROVIDER_UNAVAILABLE')); return false;
            }
            $type = TypeRegistry::current()->get((string) $this->type);
            $this->store = $type->store();
            $settings = (new Registry($this->params))->toArray();
            $this->params = (string) new Registry(DisplaySettings::normalize($settings, (string) $this->type) + $type->normalizeSettings($settings));
        } catch (\Throwable $error) {
            $this->setError(Text::_($error->getMessage())); return false;
        }
        if ($id > 0 && $db->setQuery('SELECT group_id FROM #__dharmashop_group_axes WHERE field_id=' . $id, 0, 1)->loadResult()) {
            $settings = new Registry($this->params);
            if (!(int) $settings->get('variation', 0) || (int) $settings->get('list_multiple', $settings->get('multiple', 0))) {
                $this->setError(Text::_('COM_DHARMASHOP_GROUP_AXIS_USED')); return false;
            }
        }
        $this->state = (int) $this->state;
        if (!in_array($this->state, [-2,0,1,2], true) || mb_strlen((string) $this->unit) > 20) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_SETTINGS_INVALID')); return false;
        }
        foreach (['required','in_card','in_page','all_categories','filterable'] as $flag) { $this->$flag = (int) (bool) $this->$flag; }
        if (in_array((string) $this->type, ['editor','gallery','video'], true)) { $this->in_card = 0; }
        if (in_array((string) $this->type, ['editor', 'text', 'files','gallery','video'], true)) { $this->filterable = 0; }
        $this->ordering = max(1, (int) $this->ordering);
        $this->level = 1;
        $this->is_cover = $this->sortable = 0;
        return parent::check();
    }
    public function hasValues(int $id): bool
    {
        $db = $this->getDatabase();
        foreach (['field_values','field_texts','field_media','product_axes','group_axes','category_field_values','category_field_texts','category_field_media','group_field_values','group_field_texts','group_field_media'] as $suffix) {
            $query = $db->createQuery()->select('field_id')->from($db->quoteName('#__dharmashop_' . $suffix))
                ->where('field_id = :id')->bind(':id', $id, ParameterType::INTEGER);
            if ($db->setQuery($query, 0, 1)->loadResult()) { return true; }
        }
        return false;
    }
    public function delete($pk = null): bool
    {
        $id = (int) ($pk ?? $this->id);
        if ($this->hasValues($id)) { $this->setError(Text::_('COM_DHARMASHOP_FIELD_HAS_VALUES')); return false; }
        $db = $this->getDatabase();
        $db->transactionStart();
        try {
            if (!parent::delete($id)) { $db->transactionRollback(); return false; }
            foreach (['field_options','category_fields','filter_facets','filter_ranges'] as $suffix) {
                $query = $db->createQuery()->delete($db->quoteName('#__dharmashop_' . $suffix))->where('field_id = :id')->bind(':id', $id, ParameterType::INTEGER);
                $db->setQuery($query)->execute();
            }
            $db->transactionCommit(); return true;
        } catch (\Throwable $error) { $db->transactionRollback(); throw $error; }
    }
}
