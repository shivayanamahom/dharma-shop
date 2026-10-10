<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$field = $displayData['field'];
$type = in_array($displayData['control'] ?? '', ['select', 'radio', 'color'], true) ? $displayData['control'] : 'select';
?>
<fieldset class="border-0 p-0 mt-2 mb-3 dharmaVariantChoices">
    <legend class="float-none w-auto fs-6 mb-1<?php echo empty($displayData['showTitle']) ? ' visually-hidden' : ''; ?>"><?php echo $escape($field->title); ?></legend>
    <?php echo (new FileLayout('components.dharmashop.variant-controls.' . $type, null, ['component' => 'none', 'client' => 0]))->render($displayData); ?>
</fieldset>
