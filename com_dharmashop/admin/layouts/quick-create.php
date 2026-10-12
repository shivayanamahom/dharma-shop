<?php
/** Native Joomla modal for picking a field on an already-saved catalog form. */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$taskPrefix = (string) ($displayData['taskPrefix'] ?? 'product');
$canCreate = (bool) ($displayData['canCreate'] ?? false);
$autoOpen = ($displayData['autoCreate'] ?? '') === 'fields';
$target = $taskPrefix === 'category' ? 'category' : 'product';
$fieldIds = array_values(array_filter(array_map('intval', (array) ($displayData['fieldIds'] ?? []))));
$fieldsUrl = Route::_('index.php?option=com_dharmashop&view=quickfields&layout=modal&target=' . $target . '&field_ids=' . implode(',', $fieldIds) . '&tmpl=component', false);
$contextQuery = '&quick_target=' . $target . '&quick_fields=' . implode(',', $fieldIds);
$fieldUrl = Route::_('index.php?option=com_dharmashop&task=field.add&tmpl=component&quick_create=1&target=' . $target . $contextQuery, false);
$groupUrl = Route::_('index.php?option=com_dharmashop&task=fieldgroup.add&tmpl=component&quick_create=1&target=' . $target . $contextQuery, false);
$footer = $canCreate
    ? '<button type="button" class="btn btn-outline-secondary" data-quick-create-kind="fieldgroup" data-url="' . htmlspecialchars($groupUrl, ENT_QUOTES, 'UTF-8') . '"><span class="icon-layer-group" aria-hidden="true"></span> ' . htmlspecialchars(Text::_('COM_DHARMASHOP_QUICK_CREATE_FIELDGROUP_BUTTON'), ENT_QUOTES, 'UTF-8') . '</button>'
        . '<button type="button" class="btn btn-primary" data-quick-create-kind="field" data-url="' . htmlspecialchars($fieldUrl, ENT_QUOTES, 'UTF-8') . '"><span class="icon-plus" aria-hidden="true"></span> ' . htmlspecialchars(Text::_('COM_DHARMASHOP_QUICK_CREATE_FIELD_BUTTON'), ENT_QUOTES, 'UTF-8') . '</button>'
    : '';
$footer .= '<button type="button" class="btn btn-outline-secondary" data-quick-editor-back hidden>' . htmlspecialchars(Text::_('COM_DHARMASHOP_QUICK_FIELDS_BACK'), ENT_QUOTES, 'UTF-8') . '</button>'
    . '<button type="button" class="btn btn-success" data-quick-editor-save hidden><span class="icon-save" aria-hidden="true"></span> ' . htmlspecialchars(Text::_('JSAVE'), ENT_QUOTES, 'UTF-8') . '</button>';

HTMLHelper::_('bootstrap.modal', '#dharmashop-quick-fields-modal', ['isJoomla' => true]);
?>
<?php echo LayoutHelper::render('libraries.html.bootstrap.modal.main', [
    'selector' => 'dharmashop-quick-fields-modal',
    'params' => [
        'title' => Text::_('COM_DHARMASHOP_QUICK_FIELDS'),
        'url' => htmlspecialchars($fieldsUrl, ENT_QUOTES, 'UTF-8'),
        'modalWidth' => 90,
        'bodyHeight' => 75,
        'footer' => $footer,
    ],
    'body' => '',
]); ?>
<?php if ($autoOpen) : ?><span class="visually-hidden" data-quick-create-open-on-load></span><?php endif; ?>
