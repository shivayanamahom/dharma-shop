<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopRelated\Field;
use Dharma\Plugin\System\DharmaShopRelated\Support\Blocks;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
final class RelatedfieldsField extends ListField
{
    protected $type = 'Relatedfields';
    /** Проверяем строку subform до записи настроек: Joomla сохраняет исходную форму в сессии. */
    public function validate($value, $group = null, ?Registry $input = null)
    {
        $prefix = $group ? $group . '.' : '';
        if ($input?->get($prefix . 'mode') === 'fields' && !Blocks::ids($value)) {
            $title = trim(strip_tags((string) $input->get($prefix . 'title', '')));
            $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return new \UnexpectedValueException(Text::sprintf('PLG_SYSTEM_DHARMASHOPRELATED_FIELDS_REQUIRED', $title));
        }
        return parent::validate($value, $group, $input);
    }
    protected function getOptions(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->select('id, title')->from($db->quoteName('#__dharmashop_fields'))
            ->where("target = 'product'")->where('state = 1')->where("store IN ('option','number','range','string')")->order('ordering, id');
        $options = parent::getOptions();
        foreach ($db->setQuery($query)->loadObjectList() as $field) {
            $options[] = HTMLHelper::_('select.option', $field->id, $field->title);
        }
        return $options;
    }
}
