<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
/**
 * Контекст изображения в таблице размеров. Таких строк десятки, а список контекстов у всех один:
 * в разметку строки кладём только выбранное значение, остальные значения подставляет скрипт при первом
 * фокусе/нажатии (список приходит один раз через script options). Без fancy-select: страница настроек
 * не создаёт десятки экземпляров Choices.js и тысячи лишних узлов.
 */
class ImagecontextField extends ListField
{
    protected $type = 'Imagecontext';
    private static bool $assets = false;
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (ImageContexts::SIZES as $context => $size) $options[] = HTMLHelper::_('select.option', $context, Text::_(ImageContexts::label($context)));
        return $options;
    }
    protected function getInput(): string
    {
        $options = $this->getOptions();
        $current = null;
        foreach ($options as $option) {
            if ((string) $option->value === (string) $this->value) { $current = $option; break; }
        }
        if (!self::$assets) {
            self::$assets = true;
            $document = Factory::getApplication()->getDocument();
            $document->addScriptOptions('com_dharmashop.imageContexts', array_map(static fn ($option): array => [(string) $option->value, (string) $option->text], $options));
            $document->getWebAssetManager()->useScript('com_dharmashop.image-context');
        }
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $class = trim('form-select ' . $this->class);
        $html = '<select id="' . $escape($this->id) . '" name="' . $escape($this->name) . '" class="' . $escape($class) . '" data-dharma-lazy-options'
            . ($this->required ? ' required' : '') . ($this->disabled ? ' disabled' : '') . '>';
        $html .= $current !== null
            ? '<option value="' . $escape($current->value) . '" selected>' . $escape($current->text) . '</option>'
            : '<option value="" selected>' . $escape(Text::_('JGLOBAL_SELECT_AN_OPTION')) . '</option>';

        return $html . '</select>';
    }
}
