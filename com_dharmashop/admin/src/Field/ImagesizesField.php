<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Form\Field\SubformField;
\defined('_JEXEC') or die;
class ImagesizesField extends SubformField
{
    protected $type = 'Imagesizes';
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        if (!parent::setup($element, $value, $group)) return false;
        if ($this->value === null) $this->value = ImageContexts::defaults();
        else $this->value = ImageContexts::categoryModuleDefaults($this->value);
        return true;
    }
}
