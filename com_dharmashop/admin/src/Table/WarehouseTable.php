<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

/** Склад: адрес, контакты, часы работы, срок готовности самовывоза, координаты (lat/lon). */
class WarehouseTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_warehouses', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    public function check(): bool
    {
        $this->title      = trim(strip_tags((string) $this->title));
        $this->alias      = ApplicationHelper::stringURLSafe(trim((string) $this->alias) ?: $this->title);
        $this->image      = trim((string) $this->image);
        $this->address    = trim(strip_tags((string) $this->address));
        $this->phone      = trim(strip_tags((string) $this->phone));
        $this->email      = trim(strip_tags((string) $this->email));
        $this->hours      = trim(strip_tags((string) $this->hours));
        $this->info       = trim(strip_tags((string) $this->info));
        $this->ready_term = trim(strip_tags((string) $this->ready_term));
        $this->state      = (int) $this->state;
        $this->access     = max(1, (int) $this->access);
        $this->ordering   = (int) $this->ordering;

        if ($this->title === '' || mb_strlen($this->title) > 255 || $this->alias === '' || mb_strlen($this->alias) > 190
            || mb_strlen($this->image) > 500 || mb_strlen($this->address) > 1000 || mb_strlen($this->phone) > 100 || mb_strlen($this->ready_term) > 100
            || mb_strlen($this->hours) > 1000 || mb_strlen($this->info) > 2000 || !in_array($this->state, [0, 1], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_WAREHOUSE_INVALID'));

            return false;
        }

        if ($this->email !== '' && (mb_strlen($this->email) > 190 || !MailHelper::isEmailAddress($this->email))) {
            $this->setError(Text::_('COM_DHARMASHOP_WAREHOUSE_EMAIL_INVALID'));

            return false;
        }

        if (($this->lat === null) !== ($this->lon === null)) {
            $this->setError(Text::_('COM_DHARMASHOP_WAREHOUSE_COORDS_INVALID'));

            return false;
        }

        $db    = $this->getDatabase();
        $id    = (int) $this->id;
        $alias = (string) $this->alias;
        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_warehouses'))->where('alias = :a AND id <> :id')
            ->bind(':a', $alias)->bind(':id', $id, ParameterType::INTEGER);

        if ($db->setQuery($query, 0, 1)->loadResult()) {
            $this->setError(Text::_('COM_DHARMASHOP_WAREHOUSE_ALIAS_EXISTS'));

            return false;
        }

        if (!$this->id && (int) $this->ordering === 0) {
            $this->ordering = 1 + (int) $db->setQuery('SELECT COALESCE(MAX(ordering), 0) FROM ' . $db->quoteName('#__dharmashop_warehouses'))->loadResult();
        }

        return parent::check();
    }
}
