<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
class ImagecontextField extends ListField
{
    protected $type = 'Imagecontext';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (ImageContexts::SIZES as $context => $size) $options[] = HTMLHelper::_('select.option', $context, Text::_(ImageContexts::label($context)));
        return $options;
    }
}
