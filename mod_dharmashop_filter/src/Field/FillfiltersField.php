<?php
declare(strict_types=1);
namespace Dharma\Module\DharmaShopFilter\Site\Field;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;

/** Enhances the native repeatable subform; no AJAX and no custom storage. */
final class FillfiltersField extends FormField
{
    protected $type='Fillfilters';
    protected function getInput(): string
    {
        Factory::getApplication()->getDocument()->getWebAssetManager()->registerAndUseScript('mod_dharmashop_filter.config','mod_dharmashop_filter/filter-config.js', ['version'=>'auto'], ['defer'=>true], ['core','webcomponent.field-subform']);
        return '<button type="button" class="btn btn-outline-primary" data-filter-fill>' . htmlspecialchars(Text::_('MOD_DHARMASHOP_FILTER_FILL_ALL'),ENT_QUOTES,'UTF-8') . '</button>';
    }
}
