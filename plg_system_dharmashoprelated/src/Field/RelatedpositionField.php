<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopRelated\Field;
use Dharma\Plugin\System\DharmaShopRelated\Support\Blocks;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
final class RelatedpositionField extends ListField
{
    protected $type = 'Relatedposition';
    protected function getOptions(): array
    {
        $options = [HTMLHelper::_('select.option', '', Text::_('PLG_SYSTEM_DHARMASHOPRELATED_POSITION_NONE'))];
        foreach (Blocks::POSITIONS as $position) {
            $options[] = HTMLHelper::_('select.option', $position, Text::_('PLG_SYSTEM_DHARMASHOPRELATED_POSITION_' . strtoupper($position)));
        }
        return array_merge(parent::getOptions(), $options);
    }
}
