<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$form = $displayData['form'];
$axes = array_filter($form->getGroup(''), static fn($field) => str_starts_with($field->fieldname, 'axis_'));
$axisColumn = count($axes) <= 2 ? 'col-sm-6' : 'col-sm-6 col-xl-4';
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="subform-repeatable-group border rounded p-3 mb-3 form-vertical" data-base-name="<?php echo $escape($displayData['basegroup']); ?>" data-group="<?php echo $escape($displayData['group']); ?>">
    <div class="d-flex align-items-start gap-3">
        <div class="flex-grow-1"><?php echo $form->renderField('title_preview'); ?></div>
        <button type="button" class="group-remove btn btn-outline-danger btn-sm" aria-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>"><span class="icon-trash" aria-hidden="true"></span></button>
    </div>
    <div class="row g-3">
        <?php foreach ($axes as $field) : ?>
        <div class="<?php echo $axisColumn; ?>"><?php echo $field->renderField(); ?></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3 border-top pt-3 mt-1">
        <?php foreach (['price', 'stock_qty', 'product_state'] as $name) : ?>
        <div class="col-sm-4"><?php echo $form->renderField($name); ?></div>
        <?php endforeach; ?>
    </div>
    <details>
        <summary class="text-primary mb-3"><?php echo Text::_('COM_DHARMASHOP_GENERATOR_ROW_DETAILS'); ?></summary>
        <div class="row g-3">
            <div class="col-md-7"><?php echo $form->renderField('custom_title'); ?></div>
            <div class="col-md-5"><?php echo $form->renderField('sku'); ?></div>
            <div class="col-12"><?php echo $form->renderField('image'); ?></div>
        </div>
    </details>
</div>
