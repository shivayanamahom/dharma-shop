<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
class OrderstatusField extends ListField
{
    protected $type = 'Orderstatus';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select(['id','title'])->from($db->quoteName('#__dharmashop_statuses'))->order('ordering,id');
        if ((string) $this->element['active_only'] === 'true') $query->where('state=1 AND stock_effect=0');
        $rows = $db->setQuery($query)->loadObjectList();
        foreach ($rows as $row) $options[] = (object) ['value'=>(string) $row->id,'text'=>$row->title];
        return $options;
    }
}
