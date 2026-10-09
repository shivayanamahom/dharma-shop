<?php
/** Один выбор из значений поля радиокнопками («Любое» снимает условие). Данные: section, module. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$any = !array_filter($section['options'], static fn (array $option): bool => $option['checked']);
?>
<div class="d-flex flex-column gap-1">
    <div class="form-check">
        <input class="form-check-input" type="radio" name="<?php echo $escape($section['name']); ?>" value="" id="dharmashop-filter-<?php echo $module . '-' . md5($section['name']); ?>-any"<?php echo $any ? ' checked' : ''; ?>>
        <label class="form-check-label" for="dharmashop-filter-<?php echo $module . '-' . md5($section['name']); ?>-any"><?php echo Text::_('MOD_DHARMASHOP_FILTER_ANY'); ?></label>
    </div>
    <?php foreach ($section['options'] as $i => $option) : $id = 'dharmashop-filter-' . $module . '-' . md5($section['name'] . $i); ?>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="<?php echo $escape($section['name']); ?>" value="<?php echo $escape($option['value']); ?>" id="<?php echo $id; ?>"<?php echo $option['checked'] ? ' checked' : ''; ?><?php echo $option['disabled'] ? ' disabled' : ''; ?>>
            <label class="form-check-label<?php echo $option['disabled'] ? ' text-body-secondary' : ''; ?>" for="<?php echo $id; ?>"><?php echo $escape($option['label']); ?><?php if ($option['count'] !== null) : ?> <span class="text-body-secondary small">(<?php echo (int) $option['count']; ?>)</span><?php endif; ?></label>
        </div>
    <?php endforeach; ?>
</div>
