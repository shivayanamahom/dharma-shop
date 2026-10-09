<?php
/** Нативные вкладки плагинов: fieldset extension_* в форме товара. */
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
$form = $displayData['form'];
$tabSet = $displayData['tabSet'];
foreach ($form->getFieldsets() as $fieldset) {
    if (!str_starts_with($fieldset->name, 'extension_') || !$form->getFieldset($fieldset->name)) {
        continue;
    }
    echo HTMLHelper::_('uitab.addTab', $tabSet, $fieldset->name, Text::_($fieldset->label));
    echo '<fieldset class="options-form">';
    echo $form->renderFieldset($fieldset->name);
    echo '</fieldset>';
    echo HTMLHelper::_('uitab.endTab');
}
