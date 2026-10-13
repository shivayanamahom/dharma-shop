<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Promotion\TypeRegistry;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
final class PromotiontypeField extends ListField
{
    protected $type = 'Promotiontype';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (TypeRegistry::current()->all() as $id=>$type) $options[] = HTMLHelper::_('select.option',$id,Text::_($type->label()));
        return $options;
    }
}
