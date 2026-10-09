<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$escape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = (string) $displayData['id'];
$locked = $displayData['disabled'] || $displayData['readonly'];
$required = (bool) $displayData['required'];
?>
<fieldset id="<?php echo $escape($id); ?>" class="m-0 p-0 border-0<?php echo $required ? ' required radio' : ''; ?>"<?php echo $locked ? ' disabled' : ''; ?><?php echo $displayData['dataAttribute']; ?>>
    <legend class="visually-hidden"><?php echo $escape($displayData['label']); ?></legend>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <?php foreach ($displayData['options'] as $i => $option) : $optionId = $id . '-' . $i; ?>
            <div class="form-check d-inline-flex align-items-center gap-2 mb-0">
                <input class="form-check-input mt-0" type="radio" id="<?php echo $escape($optionId); ?>" name="<?php echo $escape($displayData['name']); ?>" value="<?php echo $escape($option->value); ?>"<?php echo (string) $displayData['value'] === (string) $option->value ? ' checked' : ''; ?><?php echo $required ? ' required' : ''; ?><?php echo !empty($option->disable) ? ' disabled' : ''; ?><?php echo $displayData['autofocus'] && $i === 0 ? ' autofocus' : ''; ?><?php echo $displayData['description'] ? ' aria-describedby="' . $escape($id . '-desc') . '"' : ''; ?>>
                <label class="form-check-label d-inline-flex align-items-center gap-2" for="<?php echo $escape($optionId); ?>">
                    <?php echo LayoutHelper::render('components.dharmashop.fields.swatch', ['color' => $option->color, 'image' => $option->image, 'context' => 'field.color.admin']); ?>
                    <span><?php echo $escape($option->value === '' ? Text::_('COM_DHARMASHOP_FIELD_EMPTY') : $option->text); ?></span>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
</fieldset>
<?php if ($displayData['readonly'] && !$displayData['disabled']) : ?>
    <input type="hidden" name="<?php echo $escape($displayData['name']); ?>" value="<?php echo $escape($displayData['value']); ?>">
<?php endif; ?>
