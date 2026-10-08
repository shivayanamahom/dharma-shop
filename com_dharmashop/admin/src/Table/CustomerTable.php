<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
\defined('_JEXEC') or die;

class CustomerTable extends Table
{
    protected $_supportNullValue = true;
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_customers', 'id', $db, $dispatcher);
    }
    public function check(): bool
    {
        foreach (['name'=>255,'email'=>255,'phone'=>50,'note'=>2000] as $field=>$limit) {
            $this->$field = trim(strip_tags((string) $this->$field));
            if (mb_strlen($this->$field) > $limit) {
                $this->setError(Text::_('COM_DHARMASHOP_CUSTOMER_INVALID')); return false;
            }
        }
        if ($this->name === '' && $this->email === '' && $this->phone === '') {
            $this->setError(Text::_('COM_DHARMASHOP_CUSTOMER_CONTACT_REQUIRED')); return false;
        }
        if ($this->email !== '' && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->setError(Text::_('COM_DHARMASHOP_CUSTOMER_INVALID')); return false;
        }
        $this->phone_norm = preg_replace('/\D/', '', $this->phone);
        if (strlen($this->phone_norm) > 20) {
            $this->setError(Text::_('COM_DHARMASHOP_CUSTOMER_INVALID')); return false;
        }
        if (!$this->id) { $this->created = Factory::getDate()->toSql(); $this->user_id = null; }
        else $this->modified = Factory::getDate()->toSql();
        return parent::check();
    }
}
