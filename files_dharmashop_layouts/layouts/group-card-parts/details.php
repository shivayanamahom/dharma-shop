<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$choice = new FileLayout('components.dharmashop.group-card-parts.choice', null, ['component' => 'none', 'client' => 0]);
$ajax = !empty($displayData['ajax']);
?>
<?php foreach ($displayData['choiceFields'] as $field) :
    $field = (object) $field;
    $control = (string) $field->params->get($field->type === 'color' ? 'color_group_card_display' : 'group_card_display', 'off');
    if ($ajax ? ($control === 'off' || !(int) $field->params->get('variation', 0)) : !(int) $field->params->get('group_card_show', 1)) { continue; }
    $showTitle = (int) $field->params->get('group_card_title', 1) === 1;
    $selected = (int) ($displayData['selection'][$field->id] ?? 0);
    $id = $displayData['instance'] . '-field-' . (int) $field->id;
?>
    <fieldset class="border-0 p-0 mt-2 mb-0 dharmaGroupChoices">
        <legend class="float-none w-auto fs-6 mb-1<?php echo $showTitle ? ' small text-body-secondary' : ' visually-hidden'; ?>"><?php echo $escape($field->title); ?></legend>
        <?php if ($ajax && $control === 'select') : ?>
            <label class="visually-hidden" for="<?php echo $escape($id); ?>"><?php echo $escape($field->title); ?></label>
            <select id="<?php echo $escape($id); ?>" class="form-select form-select-sm" data-group-choice="<?php echo (int) $field->id; ?>">
                <?php foreach ($field->values as $value) : ?>
                    <option value="<?php echo (int) $value->id; ?>"<?php echo $selected === (int) $value->id ? ' selected' : ''; ?>><?php echo $escape($value->title); ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($ajax) : ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
            <?php foreach ($field->values as $value) : $optionId = $id . '-' . (int) $value->id; ?>
                <?php if ($control === 'color') : ?>
                    <input type="radio" class="btn-check" id="<?php echo $escape($optionId); ?>" name="<?php echo $escape($id); ?>" value="<?php echo (int) $value->id; ?>" data-group-choice="<?php echo (int) $field->id; ?>"<?php echo $selected === (int) $value->id ? ' checked' : ''; ?>>
                    <label class="btn btn-outline-secondary rounded-pill p-1 d-inline-flex align-items-center gap-1" for="<?php echo $escape($optionId); ?>" title="<?php echo $escape($value->title); ?>">
                        <?php echo $choice->render(['field' => $field, 'value' => $value, 'forceColor' => true]); ?>
                    </label>
                <?php else : ?>
                    <div class="form-check mb-0">
                        <input type="radio" class="form-check-input" id="<?php echo $escape($optionId); ?>" name="<?php echo $escape($id); ?>" value="<?php echo (int) $value->id; ?>" data-group-choice="<?php echo (int) $field->id; ?>"<?php echo $selected === (int) $value->id ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="<?php echo $escape($optionId); ?>"><?php echo $escape($value->title); ?></label>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        <?php else : $format = (string) $field->params->get('group_card_format', 'image_title'); ?>
            <div class="d-flex flex-wrap align-items-center gap-2<?php echo $format === 'list' ? ' flex-column align-items-start' : ''; ?>">
            <?php foreach ($field->values as $n => $value) : ?>
                <span class="d-inline-flex align-items-center gap-1 small" title="<?php echo $escape($value->title); ?>"><?php echo $choice->render(['field' => $field, 'value' => $value]); ?><?php echo $format === 'comma' && $n < count($field->values) - 1 ? ',' : ''; ?></span>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>
<?php endforeach; ?>
<?php if ($ajax) : ?>
    <p class="small text-danger mt-2 mb-0" data-group-message role="status" aria-live="polite"></p>
<?php endif; ?>
