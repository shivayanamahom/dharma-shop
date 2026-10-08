<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Table;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
\defined('_JEXEC') or die;
class StatusTable extends Table
{
    protected $_supportNullValue = true;
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_statuses','id',$db,$dispatcher);
        $this->setColumnAlias('published','state');
    }
    public function check(): bool
    {
        $this->title = trim(strip_tags((string) $this->title));
        $this->alias = ApplicationHelper::stringURLSafe(trim((string) $this->alias) ?: $this->title);
        $this->color = (string) $this->color;
        $this->state = (int) $this->state;
        $this->is_default = (int) $this->is_default;
        $this->ordering = max(1,(int) $this->ordering);
        $this->customer_message = trim(strip_tags((string) $this->customer_message));
        if ($this->title === '' || mb_strlen($this->title)>255 || $this->alias === '' || mb_strlen($this->alias)>190 || mb_strlen($this->customer_message)>2000
            || !in_array($this->color,['primary','secondary','success','danger','warning','info','dark'],true)
            || !in_array($this->state,[0,1],true) || !in_array($this->is_default,[0,1],true) || !in_array((int) $this->stock_effect,[0,1],true)) {
            $this->setError(Text::_('COM_DHARMASHOP_STATUS_INVALID')); return false;
        }
        if ($this->is_default && ($this->state !== 1 || (int) $this->stock_effect !== 0)) {
            $this->setError(Text::_('COM_DHARMASHOP_STATUS_DEFAULT_PUBLISHED')); return false;
        }
        $db = $this->getDatabase(); $id = (int) $this->id; $alias = (string) $this->alias;
        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_statuses'))->where('alias=:a AND id<>:id')->bind(':a',$alias)->bind(':id',$id,ParameterType::INTEGER);
        if ($db->setQuery($query,0,1)->loadResult()) { $this->setError(Text::_('COM_DHARMASHOP_STATUS_ALIAS_EXISTS')); return false; }
        if (!$this->is_default) {
            $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_statuses'))->where('is_default=1 AND state=1 AND stock_effect=0 AND id<>:id')->bind(':id',$id,ParameterType::INTEGER);
            if (!$db->setQuery($query,0,1)->loadResult()) { $this->setError(Text::_('COM_DHARMASHOP_STATUS_DEFAULT_REQUIRED')); return false; }
        }
        return parent::check();
    }
    public function store($updateNulls = true): bool
    {
        if (!parent::store($updateNulls)) return false;
        if ((int) $this->is_default === 1) {
            $db = $this->getDatabase(); $id = (int) $this->id;
            $query = $db->createQuery()->update($db->quoteName('#__dharmashop_statuses'))->set('is_default=CASE WHEN id=:chosen THEN 1 ELSE 0 END')->where('is_default=1 OR id=:target')->bind(':chosen',$id,ParameterType::INTEGER)->bind(':target',$id,ParameterType::INTEGER);
            $db->setQuery($query)->execute();
        }
        return true;
    }
}
