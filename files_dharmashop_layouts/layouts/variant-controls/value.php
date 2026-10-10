<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$value = $displayData['value'];
$field = $displayData['field'];
$format = (string) ($displayData['format'] ?? 'image_title');
if (!empty($displayData['forceColor'])) { $format = $format === 'image' ? 'image' : 'image_title'; }
$hasImage = ($field->type === 'color' && (string) ($value->color ?? '') !== '') || (string) ($value->image ?? '') !== '';
$image = in_array($format, ['image', 'image_title'], true) && $hasImage;
?>
<?php if ($image) : ?>
    <?php echo (new FileLayout('components.dharmashop.fields.swatch', null, ['component' => 'none', 'client' => 0]))->render([
        'color' => $field->type === 'color' ? ($value->color ?? '') : '', 'image' => $value->image ?? '', 'label' => $value->title, 'context' => 'group.choice', 'round' => $field->type === 'color',
    ]); ?>
<?php endif; ?>
<span<?php echo $format === 'image' && $image ? ' class="visually-hidden"' : ''; ?>><?php echo $escape($value->title); ?></span>
