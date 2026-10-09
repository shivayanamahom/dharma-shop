<?php
/**
 * Значения поля флажками (мультивыбор). Данные: section (name, options [value, label, count, checked, disabled], limit), module.
 * Лишние значения (limit > 0) прячутся за «Ещё» без JavaScript.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$limit = (int) ($section['limit'] ?? 0);
$options = $section['options'];
$visible = $limit > 0 && count($options) > $limit + 1 ? array_slice($options, 0, $limit) : $options;
$rest = $visible === $options ? [] : array_slice($options, $limit);
$print = static function (array $option, int $index) use ($section, $module, $escape): void {
    $id = 'dharmashop-filter-' . $module . '-' . md5($section['name'] . $index);
    ?>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="<?php echo $escape($section['name']); ?>" value="<?php echo $escape($option['value']); ?>" id="<?php echo $id; ?>"<?php echo $option['checked'] ? ' checked' : ''; ?><?php echo $option['disabled'] ? ' disabled' : ''; ?>>
        <label class="form-check-label<?php echo $option['disabled'] ? ' text-body-secondary' : ''; ?>" for="<?php echo $id; ?>"><?php echo $escape($option['label']); ?><?php if ($option['count'] !== null) : ?> <span class="text-body-secondary small">(<?php echo (int) $option['count']; ?>)</span><?php endif; ?></label>
    </div>
    <?php
};
?>
<div class="d-flex flex-column gap-1">
    <?php foreach ($visible as $i => $option) { $print($option, $i); } ?>
    <?php if ($rest) : ?>
        <details>
            <summary class="small text-primary"><?php echo Text::sprintf('MOD_DHARMASHOP_FILTER_MORE', count($rest)); ?></summary>
            <div class="d-flex flex-column gap-1 pt-1"><?php foreach ($rest as $i => $option) { $print($option, $limit + $i); } ?></div>
        </details>
    <?php endif; ?>
</div>
