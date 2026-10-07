<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
final class FieldgroupselectField extends ListField
{
    protected $type = 'Fieldgroupselect';
    protected function getOptions(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select($db->quoteName(['id', 'title', 'target']))
            ->from($db->quoteName('#__dharmashop_fieldgroups'))->where($db->quoteName('state') . ' <> -2')
            ->order($db->quoteName('ordering') . ' ASC')->order($db->quoteName('id') . ' ASC');
        $options = parent::getOptions();
        foreach ($db->setQuery($query)->loadObjectList() as $group) {
            $option = HTMLHelper::_('select.option', $group->id, $group->title);
            $conditions = FormHelper::parseShowOnConditions('target:' . $group->target, $this->formControl, $this->group);
            $option->optionattr = " data-showon='" . json_encode($conditions) . "'";
            $options[] = $option;
        }
        return $options;
    }
}
