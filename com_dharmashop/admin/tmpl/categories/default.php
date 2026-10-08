<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Categories\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');

$user = $this->getCurrentUser();
$userId = (int) $user->id;
$extension = $this->extension;
$state = $this->getModel()->getState();
$listOrder = $this->escape((string) $state->get('list.ordering'));
$listDirn = $this->escape((string) $state->get('list.direction'));
$saveOrder = $listOrder === 'a.lft' && strtolower($listDirn) === 'asc';
$isBadge = $extension === CategoryTypes::BADGE;
$isBrand = $extension === CategoryTypes::BRAND;
$filtered = !empty($this->activeFilters);

$saveOrderingUrl = 'index.php?option=com_dharmashop&task=categories.saveOrderAjax&extension=' . $extension . '&tmpl=component&' . Session::getFormToken() . '=1';

if ($saveOrder && !empty($this->items)) {
    HTMLHelper::_('draggablelist.draggable');
}

$ordering = $this->ordering;
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=categories&extension=' . $extension); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items) && ($filtered || $isBadge)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="categoryList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_DHARMASHOP_CATEGORIES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', '', 'a.lft', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell"><span class="icon-image" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_CAT_HEADING_IMAGE'); ?></span></th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-20 d-none d-md-table-cell"><?php echo Text::_('COM_DHARMASHOP_CAT_HEADING_DISPLAY'); ?></th>
                                <th scope="col" class="w-10 d-none d-md-table-cell text-center"><?php echo Text::_('COM_DHARMASHOP_CAT_HEADING_PRODUCTS'); ?></th>
                                <th scope="col" class="w-10 d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ACCESS'); ?></th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody<?php echo $saveOrder ? ' class="js-draggable" data-url="' . $saveOrderingUrl . '" data-direction="' . strtolower($listDirn) . '" data-nested="true"' : ''; ?>>
                            <?php if (!$isBadge && !$filtered) : ?>
                                <?php // Корневая запись: без выбора и перетаскивания, но с правкой страницы раздела (заголовок, тексты, SEO). ?>
                                <tr class="row-root" title="<?php echo Text::_('COM_DHARMASHOP_ROOT_NOTE', true); ?>">
                                    <td class="text-center"><input type="checkbox" class="form-check-input" disabled aria-label="<?php echo Text::_('COM_DHARMASHOP_ROOT_CATEGORY', true); ?>"></td>
                                    <td class="text-center d-none d-md-table-cell"><span class="sortable-handler inactive"><span class="icon-ellipsis-v"></span></span></td>
                                    <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', 1, 0, 'categories.', false); ?></td>
                                    <td class="text-center d-none d-md-table-cell"><span class="icon-image text-body-secondary" aria-hidden="true"></span></td>
                                    <th scope="row">
                                        <?php if ($user->authorise('core.edit', 'com_dharmashop')) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=root.edit&extension=' . $extension); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                                <?php echo Text::_($isBrand ? 'COM_DHARMASHOP_ROOT_BRAND' : 'COM_DHARMASHOP_ROOT_CATEGORY'); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo Text::_($isBrand ? 'COM_DHARMASHOP_ROOT_BRAND' : 'COM_DHARMASHOP_ROOT_CATEGORY'); ?>
                                        <?php endif; ?>
                                        <div class="small"><?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', 'root'); ?></div>
                                    </th>
                                    <td class="small d-none d-md-table-cell">
                                        <div>
                                            <?php echo Text::_($isBrand ? 'COM_DHARMASHOP_CAT_DISPLAY_BRAND_LIST' : 'COM_DHARMASHOP_CAT_DISPLAY_CATEGORY_LIST'); ?>:
                                            <span class="text-danger"><?php echo Text::_('COM_DHARMASHOP_HIDE'); ?></span>
                                        </div>
                                        <div class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_ROOT_SERVICE'); ?></div>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell text-body-secondary">—</td>
                                    <td class="small d-none d-md-table-cell text-body-secondary">—</td>
                                    <td class="d-none d-md-table-cell">1</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($this->items as $i => $item) :
                                $assetName = $extension . '.category.' . $item->id;
                                $canEdit = $user->authorise('core.edit', $assetName);
                                $canCheckin = $user->authorise('core.admin', 'com_checkin') || (int) $item->checked_out === $userId || $item->checked_out === null;
                                $canEditOwn = $user->authorise('core.edit.own', $assetName) && (int) $item->created_user_id === $userId;
                                $canChange = $user->authorise('core.edit.state', $assetName) && $canCheckin;
                                $params = new Registry($item->params);

                                // Предки записи (для перетаскивания вложенных строк), как в штатном менеджере категорий.
                                $parentsStr = '';

                                if ($item->level > 1) {
                                    $currentParent = $item->parent_id;
                                    $parentsStr = ' ' . $currentParent;

                                    for ($i2 = 0; $i2 < $item->level; $i2++) {
                                        foreach ($ordering as $k => $v) {
                                            if (in_array($currentParent, $v, false)) {
                                                $parentsStr .= ' ' . $k;
                                                $currentParent = $k;
                                                break;
                                            }
                                        }
                                    }
                                }

                                $image = trim((string) strstr(($params->get('image', '') ?: $params->get('icon', '')) . '#', '#', true));
                                ?>
                                <tr class="row<?php echo $i % 2; ?>" data-draggable-group="<?php echo (int) $item->parent_id; ?>" data-item-id="<?php echo (int) $item->id; ?>" data-parents="<?php echo $parentsStr; ?>" data-level="<?php echo (int) $item->level; ?>">
                                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php
                                        $iconClass = '';

                                        if (!$canChange) {
                                            $iconClass = ' inactive';
                                        } elseif (!$saveOrder) {
                                            $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                        }
                                        ?>
                                        <span class="sortable-handler<?php echo $iconClass; ?>"><span class="icon-ellipsis-v"></span></span>
                                        <?php if ($canChange && $saveOrder) : ?>
                                            <input type="text" class="hidden" name="order[]" size="5" value="<?php echo (int) $item->lft; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'categories.', $canChange); ?></td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php if ($image !== '') : ?>
                                            <img src="<?php echo $this->escape(\Dharma\Component\DharmaShop\Administrator\Image\ImageUrl::resolve($image, 'admin.category')); ?>" alt="" width="48" height="48" loading="lazy" style="object-fit:cover">
                                        <?php endif; ?>
                                    </td>
                                    <th scope="row">
                                        <?php $prefix = LayoutHelper::render('joomla.html.treeprefix', ['level' => $item->level]); ?>
                                        <?php echo $prefix; ?>
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'categories.', $canCheckin); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit || $canEditOwn) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=category.edit&id=' . (int) $item->id . '&extension=' . $extension); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->title); ?>">
                                                <?php echo $this->escape($item->title); ?></a>
                                        <?php else : ?>
                                            <?php echo $this->escape($item->title); ?>
                                        <?php endif; ?>
                                        <div>
                                            <?php echo $prefix; ?>
                                            <span class="small">
                                                <?php echo $item->note === null || $item->note === '' ? Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)) : Text::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note)); ?>
                                            </span>
                                        </div>
                                    </th>
                                    <td class="small d-none d-md-table-cell">
                                        <?php if ($isBadge) : ?>
                                            <span class="badge bg-<?php echo $this->escape($params->get('badge_color', 'primary')); ?>"><?php echo $this->escape($item->title); ?></span>
                                            <div><?php echo Text::_((int) $params->get('badge_show_list', 1) ? 'COM_DHARMASHOP_CAT_BADGE_IN_LISTS' : 'COM_DHARMASHOP_CAT_BADGE_PAGE_ONLY'); ?></div>
                                        <?php else : ?>
                                            <div>
                                                <?php echo Text::_($isBrand ? 'COM_DHARMASHOP_CAT_DISPLAY_BRAND_LIST' : 'COM_DHARMASHOP_CAT_DISPLAY_CATEGORY_LIST'); ?>:
                                                <?php echo (int) $params->get('show_in_list', 1) ? '<span class="text-success">' . Text::_('COM_DHARMASHOP_SHOW') . '</span>' : '<span class="text-danger">' . Text::_('COM_DHARMASHOP_HIDE') . '</span>'; ?>
                                            </div>
                                            <div>
                                                <?php echo Text::_('COM_DHARMASHOP_CAT_DISPLAY_PER_PAGE'); ?>:
                                                <?php echo (int) $params->get('list_limit', 0) > 0 ? (int) $params->get('list_limit') : Text::_('COM_DHARMASHOP_CAT_BY_DEFAULT'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <a class="btn <?php echo $item->count_published > 0 ? 'btn-success' : 'btn-secondary'; ?>" href="<?php echo Route::_('index.php?option=com_dharmashop&view=products&filter[category_id]=' . (int) $item->id . '&filter[published]=1'); ?>" title="<?php echo Text::_('COM_DHARMASHOP_CAT_COUNT_PUBLISHED'); ?>"><?php echo (int) $item->count_published; ?></a>
                                        <?php if ($item->count_total > $item->count_published) : ?>
                                            <div class="small text-body-secondary"><?php echo Text::sprintf('COM_DHARMASHOP_CAT_COUNT_TOTAL', (int) $item->count_total); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small d-none d-md-table-cell"><?php echo $this->escape((string) $item->access_level); ?></td>
                                    <td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <?php // Окно пакетной обработки (открывается из «Действий»). ?>
                <?php if ($user->authorise('core.create', 'com_dharmashop') && $user->authorise('core.edit', 'com_dharmashop')) : ?>
                    <template id="joomla-dialog-batch"><?php echo $this->loadTemplate('batch_body'); ?></template>
                <?php endif; ?>

                <input type="hidden" name="extension" value="<?php echo $this->escape($extension); ?>">
                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
