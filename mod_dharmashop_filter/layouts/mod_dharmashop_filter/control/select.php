<?php
/** Один выбор из значений поля выпадающим списком. Данные: section, module. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = 'dharmashop-filter-' . $module . '-' . md5($section['name']);
?>
<label class="visually-hidden" for="<?php echo $id; ?>"><?php echo $escape($section['title']); ?></label>
<select class="form-select form-select-sm" name="<?php echo $escape($section['name']); ?>" id="<?php echo $id; ?>">
    <option value=""><?php echo Text::_('MOD_DHARMASHOP_FILTER_ANY'); ?></option>
    <?php foreach ($section['options'] as $option) : ?>
        <option value="<?php echo $escape($option['value']); ?>"<?php echo $option['checked'] ? ' selected' : ''; ?><?php echo $option['disabled'] ? ' disabled' : ''; ?>><?php echo $escape($option['label']); ?><?php echo $option['count'] !== null ? ' (' . (int) $option['count'] . ')' : ''; ?></option>
    <?php endforeach; ?>
</select>
