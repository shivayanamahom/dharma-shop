<?php
\defined('_JEXEC') or die;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$field = $displayData['field'];
$attribute = ($displayData['attribute'] ?? '') === 'data-product-choice' ? 'data-product-choice' : 'data-group-choice';
?>
<div class="d-flex flex-wrap align-items-center gap-2">
    <?php foreach ($field->values as $value) : $id = $displayData['id'] . '-' . (int) $value->id; ?>
        <div class="form-check mb-0">
            <input type="radio" class="form-check-input" id="<?php echo $escape($id); ?>" name="<?php echo $escape($displayData['name']); ?>" value="<?php echo (int) $value->id; ?>" <?php echo $attribute; ?>="<?php echo (int) $field->id; ?>"<?php echo (int) $displayData['selected'] === (int) $value->id ? ' checked' : ''; ?> required>
            <label class="form-check-label" for="<?php echo $escape($id); ?>"><?php echo $escape($value->title); ?></label>
        </div>
    <?php endforeach; ?>
</div>
