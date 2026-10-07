<?php

/**
 * Dharma Shop — поля каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Fields\HtmlView $this */
$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$user = $this->getCurrentUser();
$state = $this->getModel()->getState();
$order = (string) $state->get('list.ordering');
$direction = (string) $state->get('list.direction');
$canEdit = $user->authorise('core.edit', 'com_dharmashop');
$canState = $user->authorise('core.edit.state', 'com_dharmashop');
$selectedTarget = (string) $state->get('filter.target');
$saveOrder = $order === 'a.ordering' && $canState && in_array($selectedTarget, ['product', 'category'], true);

if ($saveOrder) {
    HTMLHelper::_('draggablelist.draggable', 'fieldList', 'adminForm', strtolower($direction), Route::_('index.php?option=com_dharmashop&task=fields.saveOrderAjax&tmpl=component', false));
}
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=fields'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php
        // Как в списке товаров: штатный Searchtools открывает фильтры при активной отметке.
        // Отметка нужна только для макета, SQL/параметры формы её не используют.
        if (!$this->activeFilters) {
            $this->activeFilters = ['open' => '1'];
        }
        echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
        ?>
        <?php if ($order === 'a.ordering' && $canState && !$saveOrder && $this->items) : ?>
            <p class="text-muted small"><?php echo Text::_('COM_DHARMASHOP_FIELD_ORDER_TARGET_HINT'); ?></p>
        <?php endif; ?>
        <?php if (!$this->items) : ?>
            <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php else : ?>
            <table class="table" id="fieldList">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_FIELDS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $direction, $order); ?></th>
                        <th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $direction, $order); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $direction, $order); ?></th>
                        <th scope="col" class="w-15"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_FIELD_TARGET_LABEL', 'a.target', $direction, $order); ?></th>
                        <th scope="col" class="w-15"><?php echo Text::_('COM_DHARMASHOP_FIELD_TYPE_LABEL'); ?></th>
                        <th scope="col" class="w-10 text-center"><?php echo Text::_('COM_DHARMASHOP_FIELD_GROUP_LABEL'); ?></th>
                        <th scope="col" class="w-5"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $direction, $order); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) :
                    $ownLock = !(int) $item->checked_out || (int) $item->checked_out === (int) $user->id;
                    $canCheckin = $ownLock || $user->authorise('core.manage', 'com_checkin');
                    $canChange = $canState && $canCheckin;
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group="1">
                        <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
                        <td class="text-center">
                            <span class="sortable-handler<?php echo $saveOrder && $canChange ? '' : ' inactive'; ?>" title="<?php echo Text::_($saveOrder && $canChange ? 'JFIELD_ORDERING_LABEL' : 'JORDERINGDISABLED', true); ?>"><span class="icon-ellipsis-v" aria-hidden="true"></span></span>
                            <?php if ($saveOrder && $canChange) : ?>
                                <input type="text" name="order[]" value="<?php echo (int) $item->ordering; ?>" class="text-area-order d-none" aria-label="<?php echo Text::_('JFIELD_ORDERING_LABEL', true); ?>">
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo (new PublishedButton())->render((int) $item->state, $i, ['task_prefix' => 'fields.', 'disabled' => !$canChange, 'id' => 'state-' . (int) $item->id]); ?></td>
                        <th scope="row" class="has-context">
                            <?php if ($item->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'fields.', $canCheckin); ?>
                            <?php endif; ?>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=field.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->title); ?></a>
                            <?php else : ?>
                                <?php echo $this->escape($item->title); ?>
                            <?php endif; ?>
                            <div class="small text-muted"><?php echo Text::_('JFIELD_ALIAS_LABEL'); ?>: <?php echo $this->escape($item->alias); ?></div>
                        </th>
                        <td><?php echo Text::_('COM_DHARMASHOP_FIELD_TARGET_' . strtoupper((string) $item->target)); ?></td>
                        <td><?php echo Text::_('COM_DHARMASHOP_FIELD_TYPE_' . strtoupper((string) $item->type)); ?></td>
                        <td class="text-center"><?php echo $item->group_title !== null ? $this->escape($item->group_title) : Text::_('COM_DHARMASHOP_FIELD_NO_GROUP'); ?></td>
                        <td><?php echo (int) $item->id; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
