<?php
/** Characteristics in semantic definition lists; no queries in layouts. */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$showKey = ($displayData['context'] ?? '') === 'intro' ? 'show_intro' : 'show';
$fields = array_filter((array) ($displayData['fields'] ?? []), static fn (object $field): bool => !empty($field->{$showKey}) && !empty($field->values));
if (!$fields) { return; }
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$valueLayout = new FileLayout('components.dharmashop.fields.value', null, ['component' => 'none', 'client' => 0]);
$isCard = ($displayData['context'] ?? '') === 'card';
?>
<dl class="mt-3 mb-0 dharmaFields<?php echo $isCard ? ' small' : ''; ?>">
    <?php foreach ($fields as $field) : ?>
        <div class="row g-0 py-2 border-top dharmaField" data-field="<?php echo $escape($field->alias); ?>">
            <dt class="<?php echo $isCard ? 'col-7' : 'col-12 col-sm-5 col-lg-4'; ?> pe-3 fw-normal text-body-secondary text-break"><?php echo $escape($field->title); ?></dt>
            <dd class="<?php echo $isCard ? 'col-5' : 'col-12 col-sm-7 col-lg-8'; ?> mb-0 fw-medium text-break"><?php echo $valueLayout->render(['field' => $field]); ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
