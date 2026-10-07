<?php
/** Characteristics in semantic definition lists; no queries in layouts. */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$fields = array_filter((array) ($displayData['fields'] ?? []), static fn (object $field): bool => !empty($field->show) && !empty($field->values));
if (!$fields) { return; }
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$valueLayout = new FileLayout('components.dharmashop.fields.value', null, ['component' => 'none', 'client' => 0]);
?>
<dl class="mt-3 mb-0 dharmaFields<?php echo ($displayData['context'] ?? '') === 'card' ? ' small' : ''; ?>">
    <?php foreach ($fields as $field) : ?>
        <div class="row g-1 mb-2 dharmaField" data-field="<?php echo $escape($field->alias); ?>">
            <dt class="col-sm-4 fw-semibold"><?php echo $escape($field->title); ?></dt>
            <dd class="col-sm-8 mb-0"><?php echo $valueLayout->render(['field' => $field]); ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
