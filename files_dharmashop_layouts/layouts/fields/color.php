<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

$field = $displayData['field'];
$format = (string) $field->format;
$values = (array) $field->values;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$select = ($field->color_display ?? 'radio') === 'select';
?>
<?php if ($format === 'list') : ?>
    <ul class="ps-3 mb-0"><?php foreach ($values as $value) : ?><li><?php echo $escape($value->text); ?></li><?php endforeach; ?></ul>
<?php elseif ($format === 'comma') : ?>
    <?php foreach ($values as $index => $value) : ?><?php echo $index > 0 ? ', ' : ''; ?><?php echo $escape($value->text); ?><?php endforeach; ?>
<?php elseif ($select) : ?>
    <div class="d-inline-flex align-items-center gap-2 dharmaFieldColors">
        <?php foreach ($values as $value) {
            echo LayoutHelper::render('components.dharmashop.fields.swatch', ['color' => $value->color ?? '', 'image' => $value->image ?? '', 'context' => $field->image_context ?? 'field.color.page', 'label' => $value->text, 'round' => true]);
        } ?>
        <select class="form-select w-auto" disabled aria-label="<?php echo $escape($field->title); ?>">
            <?php foreach ($values as $value) : ?><option><?php echo $escape($value->text); ?></option><?php endforeach; ?>
        </select>
    </div>
<?php else : ?>
    <div class="d-flex flex-wrap align-items-center gap-2 dharmaFieldColors" role="group" aria-label="<?php echo $escape($field->title); ?>">
        <?php foreach ($values as $value) :
            $label = (string) $value->text;
            $hasSwatch = preg_match('/^#[0-9a-f]{6}$/iD', (string) ($value->color ?? '')) || !empty($value->image);
        ?>
            <span class="d-inline-flex align-items-center gap-2" title="<?php echo $escape($label); ?>">
                <?php echo LayoutHelper::render('components.dharmashop.fields.swatch', ['color' => $value->color ?? '', 'image' => $value->image ?? '', 'context' => $field->image_context ?? 'field.color.page', 'label' => $format === 'image' ? $label : '', 'round' => true]); ?>
                <?php if ($format !== 'image' || !$hasSwatch) : ?><span><?php echo $escape($label); ?></span><?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
