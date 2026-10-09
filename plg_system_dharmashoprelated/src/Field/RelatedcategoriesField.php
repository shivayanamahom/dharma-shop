<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopRelated\Field;

use Dharma\Plugin\System\DharmaShopRelated\Support\Blocks;
use Joomla\CMS\Form\Field\CategoryField;

\defined('_JEXEC') or die;

/** Нативный список категорий с динамической категорией товара по умолчанию. */
final class RelatedcategoriesField extends CategoryField
{
    public $type = 'Relatedcategories';

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        return parent::setup($element, Blocks::categories($value), $group);
    }
}
