<?php
/** One visual track, two native range handles. Data: section, module. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$section = $displayData['section'];
$module = (int) $displayData['module'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$format = static fn (?float $n): string => $n === null ? '' : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
$bounds = $section['bounds'] ?? null;
$value = $section['value'];
$id = 'dharmashop-filter-' . $module . '-' . md5($section['name']);
// No JavaScript: hidden handles, working numeric inputs in range-inputs.php.
$slider = !empty($section['slider']) && $bounds && $bounds['min'] < $bounds['max'];
$step = $section['step'];
if ($slider && $step === 'any') {
    $precision = 2;
    foreach ([$bounds['min'], $bounds['max'], $value['min'], $value['max']] as $number) {
        $fraction = explode('.', $format($number))[1] ?? '';
        $precision = max($precision, strlen($fraction));
    }
    $step = $format(10 ** -$precision);
}
?>
<?php if ($slider) : ?>
    <div class="dharmaFilterRange mb-2" data-dharmashop-slider data-from="<?php echo $id; ?>-min" data-to="<?php echo $id; ?>-max" hidden>
        <span data-slider-track aria-hidden="true"></span>
        <input type="range" data-dharmashop-slider-min aria-label="<?php echo $escape($section['title'] . ': ' . Text::_('MOD_DHARMASHOP_FILTER_FROM')); ?>"
            min="<?php echo $escape($format($bounds['min'])); ?>" max="<?php echo $escape($format($bounds['max'])); ?>" step="<?php echo $escape($step); ?>" value="<?php echo $escape($format($value['min'] ?? $bounds['min'])); ?>">
        <input type="range" data-dharmashop-slider-max aria-label="<?php echo $escape($section['title'] . ': ' . Text::_('MOD_DHARMASHOP_FILTER_TO')); ?>"
            min="<?php echo $escape($format($bounds['min'])); ?>" max="<?php echo $escape($format($bounds['max'])); ?>" step="<?php echo $escape($step); ?>" value="<?php echo $escape($format($value['max'] ?? $bounds['max'])); ?>">
    </div>
<?php endif; ?>
