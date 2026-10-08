<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
final class ImagepresetcontextField extends ListField
{
    protected $type = 'Imagepresetcontext';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (ImageContexts::presets(ComponentHelper::getParams('com_dharmashop')) as $context=>$preset) {
            $options[] = HTMLHelper::_('select.option',$context,Text::_(ImageContexts::label($context)));
        }
        return $options;
    }
}
