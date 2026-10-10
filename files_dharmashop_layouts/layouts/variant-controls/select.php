<?php
\defined('_JEXEC') or die;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$field = $displayData['field'];
$attribute = ($displayData['attribute'] ?? '') === 'data-product-choice' ? 'data-product-choice' : 'data-group-choice';
?>
<label class="visually-hidden" for="<?php echo $escape($displayData['id']); ?>"><?php echo $escape($field->title); ?></label>
<select id="<?php echo $escape($displayData['id']); ?>" name="<?php echo $escape($displayData['name']); ?>" class="form-select form-select-sm" <?php echo $attribute; ?>="<?php echo (int) $field->id; ?>" required>
    <?php foreach ($field->values as $value) : ?>
        <option value="<?php echo (int) $value->id; ?>"<?php echo (int) $displayData['selected'] === (int) $value->id ? ' selected' : ''; ?>><?php echo $escape($value->title); ?></option>
    <?php endforeach; ?>
</select>
