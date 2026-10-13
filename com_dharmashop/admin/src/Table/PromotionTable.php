<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Table;
use Dharma\Component\DharmaShop\Administrator\Promotion\TypeRegistry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
\defined('_JEXEC') or die;
final class PromotionTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null) { parent::__construct('#__dharmashop_promotions','id',$db,$dispatcher); $this->setColumnAlias('published', 'state'); }
    public function check(): bool
    {
        $this->title = trim(strip_tags((string)$this->title));
        $this->plugin = trim((string)$this->plugin);
        $type = TypeRegistry::current()->find((string)$this->plugin);
        $this->code = null;
        $this->state = (int)$this->state;
        $this->access = max(1,(int)$this->access);
        $this->ordering = (int)$this->ordering;
        foreach (['starts','ends'] as $field) $this->$field = trim((string)($this->$field ?? '')) ?: null;
        if ($this->title==='' || mb_strlen($this->title)>255 || !in_array($this->state,[0,1],true) || !$type) {
            $this->setError(Text::_('COM_DHARMASHOP_PROMOTION_INVALID')); return false;
        }
        if ($this->starts && $this->ends && $this->starts>$this->ends) { $this->setError(Text::_('COM_DHARMASHOP_PROMOTION_PERIOD_INVALID')); return false; }
        return parent::check();
    }
}
