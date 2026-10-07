<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

/** Определение группы; значения полей никогда не удаляются вместе с группой. */
class FieldgroupTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_fieldgroups', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    public function check(): bool
    {
        $this->target = (string) $this->target;

        if (!in_array($this->target, ['product', 'category'], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELD_TARGET_INVALID'));
            return false;
        }

        $this->tab_mode = (string) ($this->tab_mode ?? 'fields');

        if (!in_array($this->tab_mode, ['fields', 'group'], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_TAB_INVALID'));
            return false;
        }

        $this->title = trim(strip_tags((string) $this->title));

        if ($this->title === '') {
            $this->setError(Text::_('COM_DHARMASHOP_ERROR_TITLE_REQUIRED'));
            return false;
        }

        $alias = trim((string) $this->alias);
        $this->alias = ApplicationHelper::stringURLSafe($alias === '' ? $this->title : $alias);

        if ($this->alias === '' || trim(str_replace('-', '', $this->alias)) === '') {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_ALIAS_REQUIRED'));
            return false;
        }

        if (mb_strlen($this->title) > 255 || mb_strlen($this->alias) > 190) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_TEXT_TOO_LONG'));
            return false;
        }

        $db = $this->getDatabase();
        $id = (int) $this->id;
        if ($id > 0) {
            $previous = $db->createQuery()->select($db->quoteName('target'))
                ->from($db->quoteName('#__dharmashop_fieldgroups'))
                ->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);

            if ((string) $db->setQuery($previous)->loadResult() !== $this->target) {
                $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_TARGET_LOCKED'));
                return false;
            }
        }

        $alias = (string) $this->alias;
        $query = $db->createQuery()->select($db->quoteName('id'))
            ->from($db->quoteName('#__dharmashop_fieldgroups'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('id') . ' <> :id')
            ->bind(':alias', $alias)->bind(':id', $id, ParameterType::INTEGER);

        if ($db->setQuery($query, 0, 1)->loadResult()) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_ALIAS_EXISTS'));
            return false;
        }

        $this->state = (int) $this->state;

        if (!in_array($this->state, [-2, 0, 1, 2], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_STATE_INVALID'));
            return false;
        }

        $this->ordering = max(1, (int) $this->ordering);

        return parent::check();
    }

    public function delete($pk = null): bool
    {
        $id = (int) ($pk ?? $this->id);

        if ($this->countFields($id) > 0) {
            $this->setError(Text::_('COM_DHARMASHOP_FIELDGROUP_HAS_FIELDS'));
            return false;
        }

        return parent::delete($pk);
    }

    private function countFields(int $id): int
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select('COUNT(*)')
            ->from($db->quoteName('#__dharmashop_fields'))
            ->where($db->quoteName('group_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }
}
