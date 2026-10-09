<?php
/**
 * Диапазон «от — до» (цена, число, диапазон поля): два числовых поля; пределы — из значений каталога; быстрые интервалы цены (price_presets) — ссылки
 * на ту же страницу с границами. Данные: section (name, bounds, value, step, presets [label, url]), module.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$format = static fn (?float $n): string => $n === null ? '' : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
$bounds = $section['bounds'] ?? null;
$value = $section['value'];
$id = 'dharmashop-filter-' . $module . '-' . md5($section['name']);
// Ползунки — два штатных form-range (нижняя и верхняя граница), синхронизируются с полями скриптом filter-slider.js (без него остаются поля).
$slider = !empty($section['slider']) && $bounds && $bounds['min'] < $bounds['max'];
?>
<?php if ($slider) : ?>
    <div class="mb-2" data-dharmashop-slider>
        <input type="range" class="form-range" data-dharmashop-slider-min aria-label="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_FROM')); ?>"
            min="<?php echo $escape($format($bounds['min'])); ?>" max="<?php echo $escape($format($bounds['max'])); ?>" step="<?php echo $escape($section['step']); ?>" value="<?php echo $escape($format($value['min'] ?? $bounds['min'])); ?>">
        <input type="range" class="form-range" data-dharmashop-slider-max aria-label="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_TO')); ?>"
            min="<?php echo $escape($format($bounds['min'])); ?>" max="<?php echo $escape($format($bounds['max'])); ?>" step="<?php echo $escape($section['step']); ?>" value="<?php echo $escape($format($value['max'] ?? $bounds['max'])); ?>">
    </div>
<?php endif; ?>
<div class="row g-2">
    <div class="col-6">
        <label class="visually-hidden" for="<?php echo $id; ?>-min"><?php echo Text::_('MOD_DHARMASHOP_FILTER_FROM'); ?></label>
        <input type="number" class="form-control form-control-sm" name="<?php echo $escape($section['name']); ?>[min]" id="<?php echo $id; ?>-min" inputmode="decimal" step="<?php echo $escape($section['step']); ?>"
            value="<?php echo $escape($format($value['min'])); ?>" placeholder="<?php echo $escape($bounds ? Text::_('MOD_DHARMASHOP_FILTER_FROM') . ' ' . $format($bounds['min']) : Text::_('MOD_DHARMASHOP_FILTER_FROM')); ?>"<?php echo $bounds ? ' min="' . $escape($format($bounds['min'])) . '" max="' . $escape($format($bounds['max'])) . '"' : ''; ?>>
    </div>
    <div class="col-6">
        <label class="visually-hidden" for="<?php echo $id; ?>-max"><?php echo Text::_('MOD_DHARMASHOP_FILTER_TO'); ?></label>
        <input type="number" class="form-control form-control-sm" name="<?php echo $escape($section['name']); ?>[max]" id="<?php echo $id; ?>-max" inputmode="decimal" step="<?php echo $escape($section['step']); ?>"
            value="<?php echo $escape($format($value['max'])); ?>" placeholder="<?php echo $escape($bounds ? Text::_('MOD_DHARMASHOP_FILTER_TO') . ' ' . $format($bounds['max']) : Text::_('MOD_DHARMASHOP_FILTER_TO')); ?>"<?php echo $bounds ? ' min="' . $escape($format($bounds['min'])) . '" max="' . $escape($format($bounds['max'])) . '"' : ''; ?>>
    </div>
</div>
<?php if (!empty($section['presets'])) : ?>
    <div class="d-flex flex-wrap gap-1 mt-2">
        <?php foreach ($section['presets'] as $preset) : ?>
            <a class="badge text-bg-light border text-decoration-none fw-normal" href="<?php echo $escape($preset['url']); ?>"><?php echo $escape($preset['label']); ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
