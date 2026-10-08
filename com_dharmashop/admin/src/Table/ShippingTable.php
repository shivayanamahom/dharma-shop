<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Dharma\Component\DharmaShop\Administrator\Shipping\TypeRegistry;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

/** Способ доставки: запись справочника; настройки типа лежат в params (JSON) и проверяются типом в модели. */
class ShippingTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_shipping_methods', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    public function check(): bool
    {
        $this->title       = trim(strip_tags((string) $this->title));
        $this->alias       = ApplicationHelper::stringURLSafe(trim((string) $this->alias) ?: $this->title);
        $this->plugin      = trim((string) $this->plugin);
        $this->description = trim((string) $this->description);
        $this->image       = trim((string) $this->image);
        $this->state       = (int) $this->state;
        $this->is_default  = (int) $this->is_default;
        $this->access      = max(1, (int) $this->access);
        $this->ordering    = (int) $this->ordering;

        if ($this->title === '' || mb_strlen($this->title) > 255 || $this->alias === '' || mb_strlen($this->alias) > 190 || mb_strlen($this->description) > 5000 || mb_strlen($this->image) > 500
            || !in_array($this->state, [0, 1], true) || !in_array($this->is_default, [0, 1], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_SHIPPING_INVALID'));

            return false;
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $this->plugin)) {
            $this->setError(Text::_('COM_DHARMASHOP_SHIPPING_TYPE_REQUIRED'));

            return false;
        }

        $db    = $this->getDatabase();
        $id    = (int) $this->id;
        $alias = (string) $this->alias;
        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_shipping_methods'))->where('alias = :a AND id <> :id')
            ->bind(':a', $alias)->bind(':id', $id, ParameterType::INTEGER);

        if ($db->setQuery($query, 0, 1)->loadResult()) {
            $this->setError(Text::_('COM_DHARMASHOP_SHIPPING_ALIAS_EXISTS'));

            return false;
        }

        if (!$this->id && (int) $this->ordering === 0) {
            $this->ordering = 1 + (int) $db->setQuery('SELECT COALESCE(MAX(ordering), 0) FROM ' . $db->quoteName('#__dharmashop_shipping_methods'))->loadResult();
        }

        return parent::check();
    }

    public function store($updateNulls = true): bool
    {
        if (!parent::store($updateNulls)) {
            return false;
        }

        // Способ по умолчанию один: остальные снимаются одним UPDATE.
        if ((int) $this->is_default === 1) {
            $db     = $this->getDatabase();
            $id     = (int) $this->id;
            $query  = $db->createQuery()->update($db->quoteName('#__dharmashop_shipping_methods'))->set('is_default = 0')->where('is_default = 1 AND id <> :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $db->setQuery($query)->execute();
        }

        return true;
    }

    /** Тип должен быть известен: его плагин включён. */
    public static function typeExists(string $plugin): bool
    {
        return TypeRegistry::current()->find($plugin) !== null;
    }
}
