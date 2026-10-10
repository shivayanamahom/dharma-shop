<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$field = $displayData['field'];
$attribute = ($displayData['attribute'] ?? '') === 'data-product-choice' ? 'data-product-choice' : 'data-group-choice';
$valueLayout = new FileLayout('components.dharmashop.variant-controls.value', null, ['component' => 'none', 'client' => 0]);
?>
<div class="d-flex flex-wrap align-items-center gap-2">
    <?php foreach ($field->values as $value) : $id = $displayData['id'] . '-' . (int) $value->id; ?>
        <input type="radio" class="btn-check" id="<?php echo $escape($id); ?>" name="<?php echo $escape($displayData['name']); ?>" value="<?php echo (int) $value->id; ?>" <?php echo $attribute; ?>="<?php echo (int) $field->id; ?>"<?php echo (int) $displayData['selected'] === (int) $value->id ? ' checked' : ''; ?> required>
        <label class="btn btn-outline-secondary rounded-pill p-1 d-inline-flex align-items-center gap-1" for="<?php echo $escape($id); ?>" title="<?php echo $escape($value->title); ?>">
            <?php echo $valueLayout->render(['field' => $field, 'value' => $value, 'format' => $displayData['format'] ?? 'image_title', 'forceColor' => true]); ?>
        </label>
    <?php endforeach; ?>
</div>
