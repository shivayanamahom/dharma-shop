<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Dharma\Component\DharmaShop\Administrator\Customer\ContactKey;
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
        $this->contact_key = empty($this->user_id) ? ContactKey::guest($this->email, $this->phone_norm) : null;
        if ($this->contact_key !== null) {
            $db = $this->getDatabase();
            $key = $this->contact_key;
            $id = (int) $this->id;
            $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_customers'))
                ->where('contact_key=:key')->where('id<>:id')->bind(':key',$key)->bind(':id',$id,ParameterType::INTEGER);
            if ($db->setQuery($query,0,1)->loadResult()) {
                $this->setError(Text::_('COM_DHARMASHOP_CUSTOMER_CONTACT_DUPLICATE')); return false;
            }
        }
        return parent::check();
    }
}
