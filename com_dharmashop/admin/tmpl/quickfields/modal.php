<?php
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$this->getDocument()->getWebAssetManager()->useScript('table.columns');
$state = $this->getModel()->getState();
$ordering = (string) $state->get('list.ordering');
$direction = (string) $state->get('list.direction');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=quickfields&layout=modal&target=' . $this->target . '&field_ids=' . implode(',', $this->fieldIds)); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container container-fluid">
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_QUICK_FIELDS_DESC'); ?></p>
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (!$this->items) : ?>
            <div class="alert alert-info" role="status"><?php echo Text::_('COM_DHARMASHOP_QUICK_FIELDS_EMPTY'); ?></div>
        <?php else : ?>
            <table class="table" id="quickFieldList">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_QUICK_FIELDS'); ?></caption>
                <thead><tr>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $direction, $ordering); ?></th>
                    <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FIELD_GROUP_LABEL'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FIELD_TYPE_LABEL'); ?></th>
                    <th scope="col" class="w-1"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $direction, $ordering); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($this->items as $field) : ?>
                    <tr>
                        <th scope="row"><a href="<?php echo Route::_('index.php?option=com_dharmashop&task=field.edit&id=' . (int) $field->id . '&tmpl=component&quick_create=1&quick_target=' . $this->target . '&quick_fields=' . implode(',', $this->fieldIds)); ?>"><?php echo $this->escape($field->title); ?></a></th>
                        <td><?php echo $field->group_title !== null ? $this->escape($field->group_title) : Text::_('COM_DHARMASHOP_FIELD_NO_GROUP'); ?></td>
                        <td><?php echo $this->escape($this->typeLabels[$field->type] ?? (string) $field->type); ?></td>
                        <td><?php echo (int) $field->id; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
