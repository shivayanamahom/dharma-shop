<?php
/** Переключатель «в наличии» и подобные одиночные условия. Данные: section (name, checked, title), module. */
\defined('_JEXEC') or die;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = 'dharmashop-filter-' . $module . '-' . md5($section['name']);
?>
<div class="form-check form-switch">
    <?php if (in_array($section['kind'] ?? '', ['stock', 'sale'], true)) : ?><span class="dharmaFilterIcon dharmaFilterIcon-<?php echo $escape($section['kind']); ?>" aria-hidden="true"></span><?php endif; ?>
    <input class="form-check-input" type="checkbox" role="switch" name="<?php echo $escape($section['name']); ?>" value="1" id="<?php echo $id; ?>"<?php echo $section['checked'] ? ' checked' : ''; ?>>
    <label class="form-check-label fw-semibold" for="<?php echo $id; ?>"><?php echo $escape($section['title']); ?></label>
</div>
