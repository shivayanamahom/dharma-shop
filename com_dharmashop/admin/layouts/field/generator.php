<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="subform-repeatable-wrapper subform-layout">
<joomla-field-subform class="subform-repeatable dharma-generator-rows" name="<?php echo $escape($displayData['name']); ?>" button-add=".group-add" button-remove=".group-remove" repeatable-element=".subform-repeatable-group" minimum="0" maximum="100">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="group-add btn btn-outline-primary"><span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_GENERATOR_ADD'); ?></button>
        <button type="button" class="btn btn-outline-danger" data-generator-clear><?php echo Text::_('COM_DHARMASHOP_GENERATOR_CLEAR'); ?></button>
        <span class="badge bg-light text-dark border align-self-center p-2" data-generator-count aria-live="polite"></span>
    </div>
    <?php foreach ($displayData['forms'] as $key => $form) : ?>
        <?php echo LayoutHelper::render('field.generator-row', ['form' => $form, 'basegroup' => $displayData['fieldname'], 'group' => $displayData['fieldname'] . $key], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>
    <?php endforeach; ?>
    <template class="subform-repeatable-template-section hidden"><?php echo LayoutHelper::render('field.generator-row', ['form' => $displayData['tmpl'], 'basegroup' => $displayData['fieldname'], 'group' => $displayData['fieldname'] . 'X'], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?></template>
</joomla-field-subform>
</div>
