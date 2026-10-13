<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * Таблица колонок профиля: копия штатной раскладки `joomla.form.field.subform.repeatable-table` без обёртки `table-responsive`.
 * Эта обёртка прокручивает содержимое и обрезает выпадающий список поиска в строке таблицы, поэтому значения не видны.
 * Разметка штатная, своего CSS и JS нет. Строки выводит штатная вложенная раскладка `section`.
 *
 * @var array $displayData
 */
extract($displayData);

if ($multiple) {
    Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('webcomponent.field-subform');
}

$class = $class ? ' ' . $class : '';
$fields = $tmpl->getGroup('');
$width = empty($fields) ? 0 : 92 / \count($fields);
$head = '';

foreach ($fields as $field) {
    $head .= '<th scope="col" style="width:' . $width . '%">' . strip_tags($field->label);

    if ($field->description) {
        $head .= '<span class="icon-info-circle" aria-hidden="true" tabindex="0"></span><div role="tooltip" id="tip-' . $field->id . '">' . Text::_($field->description) . '</div>';
    }

    $head .= '</th>';
}

$row = static fn ($form, string $group) => LayoutHelper::render('joomla.form.field.subform.repeatable-table.section', ['form' => $form, 'basegroup' => $fieldname, 'group' => $group, 'buttons' => $buttons]);
?>
<div class="subform-repeatable-wrapper subform-table-layout subform-table-sublayout-section">
    <joomla-field-subform class="subform-repeatable<?php echo $class; ?> subform-repeatable-table" name="<?php echo $name; ?>"
        button-add=".group-add" button-remove=".group-remove" button-move="<?php echo empty($buttons['move']) ? '' : '.group-move'; ?>"
        repeatable-element=".subform-repeatable-group"
        rows-container="tbody.subform-repeatable-container" minimum="<?php echo $min; ?>" maximum="<?php echo $max; ?>">
        <table class="table table-bordered" id="subfieldList_<?php echo $fieldId; ?>">
            <caption class="visually-hidden"><?php echo Text::_('JGLOBAL_REPEATABLE_FIELDS_TABLE_CAPTION'); ?></caption>
            <thead>
                <tr>
                    <?php echo $head; ?>
                    <?php if (!empty($buttons)) : ?>
                    <td style="width:8%;">
                        <?php if (!empty($buttons['add'])) : ?>
                        <div class="btn-group">
                            <button type="button" class="group-add btn btn-sm btn-success" aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>">
                                <span class="icon-plus" aria-hidden="true"></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="subform-repeatable-container">
                <?php foreach ($forms as $k => $form) : ?>
                    <?php echo $row($form, $fieldname . $k); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($multiple) : ?>
        <template class="subform-repeatable-template-section hidden"><?php echo trim($row($tmpl, $fieldname . 'X')); ?></template>
        <?php endif; ?>
    </joomla-field-subform>
</div>
