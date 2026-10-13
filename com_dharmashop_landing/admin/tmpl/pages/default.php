<?php

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$user = $this->getCurrentUser();
$state = $this->getModel()->getState();
$order = (string) $state->get('list.ordering');
$direction = (string) $state->get('list.direction');
$canEdit = $user->authorise('core.edit', 'com_dharmashop_landing');
$canState = $user->authorise('core.edit.state', 'com_dharmashop_landing');
$saveOrder = $order === 'a.ordering' && $canState;

if ($saveOrder) {
    HTMLHelper::_('draggablelist.draggable', 'pageList', 'adminForm', strtolower($direction), Route::_('index.php?option=com_dharmashop_landing&task=pages.saveOrderAjax&tmpl=component', false));
}
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop_landing&view=pages'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (!$this->items) : ?>
            <div class="alert alert-info"><?php echo Text::_($this->activeFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DHARMASHOP_LANDING_PAGES_EMPTY'); ?></div>
        <?php else : ?>
            <table class="table" id="pageList">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_LANDING_PAGES_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $direction, $order); ?></th>
                        <th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $direction, $order); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $direction, $order); ?></th>
                        <th scope="col" class="w-15"><?php echo Text::_('COM_DHARMASHOP_LANDING_SOURCE'); ?></th>
                        <th scope="col" class="w-15"><?php echo Text::_('COM_DHARMASHOP_LANDING_CATID'); ?></th>
                        <th scope="col" class="w-10"><?php echo Text::_('JGRID_HEADING_ACCESS'); ?></th>
                        <th scope="col" class="w-5"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $direction, $order); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) :
                    $ownLock = !(int) $item->checked_out || (int) $item->checked_out === (int) $user->id;
                    $canCheckin = $ownLock || $user->authorise('core.manage', 'com_checkin');
                    $canChange = $canState && $canCheckin;
                    $isManual = $item->source === 'manual';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group="1">
                        <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
                        <td class="text-center">
                            <span class="sortable-handler<?php echo $saveOrder && $canChange ? '' : ' inactive'; ?>"><span class="icon-ellipsis-v" aria-hidden="true"></span></span>
                            <?php if ($saveOrder && $canChange) : ?>
                                <input type="text" name="order[]" value="<?php echo (int) $item->ordering; ?>" class="text-area-order d-none" aria-label="<?php echo Text::_('JFIELD_ORDERING_LABEL', true); ?>">
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo (new PublishedButton())->render((int) $item->state, $i, ['task_prefix' => 'pages.', 'disabled' => !$canChange, 'id' => 'state-' . (int) $item->id]); ?></td>
                        <th scope="row" class="has-context">
                            <?php if ($item->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'pages.', $canCheckin); ?>
                            <?php endif; ?>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_dharmashop_landing&task=page.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->title); ?></a>
                            <?php else : ?>
                                <?php echo $this->escape($item->title); ?>
                            <?php endif; ?>
                            <?php if ((int) $item->broken) : ?>
                                <span class="badge bg-warning text-dark"><?php echo Text::_('COM_DHARMASHOP_LANDING_BROKEN'); ?></span>
                            <?php endif; ?>
                            <div class="small text-muted"><?php echo Text::_('JFIELD_ALIAS_LABEL'); ?>: <?php echo $this->escape($item->alias); ?></div>
                        </th>
                        <td class="small">
                            <?php echo Text::_($isManual ? 'COM_DHARMASHOP_LANDING_SOURCE_MANUAL' : 'COM_DHARMASHOP_LANDING_SOURCE_FILTER'); ?>
                            <?php if ($isManual) : ?>
                                <span class="text-muted">(<?php echo (int) $item->products_count; ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo $this->escape((string) ($item->category_title ?: Text::_('COM_DHARMASHOP_LANDING_ALL_CATEGORIES'))); ?></td>
                        <td><?php echo $this->escape((string) $item->access_level); ?></td>
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
