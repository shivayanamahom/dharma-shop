<?php
/** Значения кнопками-флажками Bootstrap (btn-check). Данные: section, module. */
\defined('_JEXEC') or die;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="d-flex flex-wrap gap-2">
    <?php foreach ($section['options'] as $i => $option) : $id = 'dharmashop-filter-' . $module . '-' . md5($section['name'] . $i); ?>
        <input class="btn-check" type="checkbox" name="<?php echo $escape($section['name']); ?>" value="<?php echo $escape($option['value']); ?>" id="<?php echo $id; ?>" autocomplete="off"<?php echo $option['checked'] ? ' checked' : ''; ?><?php echo $option['disabled'] ? ' disabled' : ''; ?>>
        <label class="btn btn-outline-primary btn-sm" for="<?php echo $id; ?>"><?php echo $escape($option['label']); ?><?php echo $option['count'] !== null ? ' (' . (int) $option['count'] . ')' : ''; ?></label>
    <?php endforeach; ?>
</div>
