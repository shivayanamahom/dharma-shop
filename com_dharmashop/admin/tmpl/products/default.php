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

use Joomla\CMS\Button\PublishedButton;
use Dharma\Component\DharmaShop\Administrator\Pricing\Discount;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Products\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');

$user = $this->getCurrentUser();
$userId = (int) $user->id;
$state = $this->getModel()->getState();
$listOrder = $this->escape((string) $state->get('list.ordering'));
$listDirn = $this->escape((string) $state->get('list.direction'));
$shop = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
$money = $shop->money();

// Ссылка на запись категории, бренда или бейджа (правка — если есть право); иначе просто название.
$catLink = function (object $entry) use ($user): string {
    $title = htmlspecialchars($entry->title, ENT_QUOTES, 'UTF-8');

    if (!$user->authorise('core.edit', $entry->extension . '.category.' . $entry->id)) {
        return $title;
    }

    return '<a href="' . Route::_('index.php?option=com_dharmashop&task=category.edit&id=' . $entry->id . '&extension=' . $entry->extension) . '">' . $title . '</a>';
};
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=products'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php
                // Панель фильтров открыта сразу (удобнее для каталога с множеством привязок): штатный макет раскрывает её,
                // только если есть активные фильтры, поэтому при пустых добавляем служебную отметку (макет больше нигде её не читает).
                if (empty($this->activeFilters)) {
                    $this->activeFilters = ['open' => '1'];
                }

                echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
                ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="productList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_DHARMASHOP_PRODUCTS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell"><span class="icon-image" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_HEADING_IMAGE'); ?></span></th>
                                <th scope="col" style="min-width:100px">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_DHARMASHOP_PRODUCT_GROUP'); ?>
                                </th>
                                <th scope="col" class="w-20 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_HEADING_CATEGORIES', 'category_title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell text-end">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_HEADING_PRICE', 'a.price_min', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_HEADING_STOCK', 'a.in_stock', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) :
                                $canEdit = $user->authorise('core.edit', 'com_dharmashop.product.' . $item->id);
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === $userId || empty($item->checked_out);
                                $canEditOwn = $user->authorise('core.edit.own', 'com_dharmashop.product.' . $item->id) && (int) $item->created_by === $userId;
                                $canChange = $user->authorise('core.edit.state', 'com_dharmashop.product.' . $item->id) && $canCheckin;
                                $cover = trim((string) strstr(((string) $item->cover) . '#', '#', true));
                                ?>
                                <tr class="row<?php echo $i % 2; ?>">
                                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
                                    <td class="text-center">
                                        <?php
                                        echo (new PublishedButton())->render((int) $item->state, $i, [
                                            'task_prefix' => 'products.',
                                            'disabled' => !$canChange,
                                            'id' => 'state-' . $item->id,
                                        ]);
                                        ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php if ($cover !== '') : ?>
                                            <img src="<?php echo $this->escape(\Dharma\Component\DharmaShop\Administrator\Image\ImageUrl::resolve($cover, 'admin.product')); ?>" alt="" width="48" height="48" loading="lazy" class="rounded object-fit-cover">
                                        <?php else : ?>
                                            <span class="icon-image text-body-secondary" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </td>
                                    <th scope="row" class="has-context">
                                        <div class="break-word">
                                            <?php if ($item->checked_out) : ?>
                                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'products.', $canCheckin); ?>
                                            <?php endif; ?>
                                            <?php if ($canEdit || $canEditOwn) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=product.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->title); ?>">
                                                    <?php echo $this->escape($item->title); ?></a>
                                            <?php else : ?>
                                                <?php echo $this->escape($item->title); ?>
                                            <?php endif; ?>
                                            <div class="small break-word">
                                                <?php echo Text::_('JFIELD_ALIAS_LABEL'); ?>: <?php echo $this->escape($item->alias); ?>
                                                <?php if ($item->sku) : ?>
                                                    <br><?php echo Text::_('COM_DHARMASHOP_SKU'); ?>: <?php echo $this->escape($item->sku); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </th>
                                    <td class="d-none d-md-table-cell break-word small">
                                        <?php if ($item->group_title !== null) : ?>
                                            <?php if ($user->authorise('core.edit', 'com_dharmashop')) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=productgroup.edit&id=' . (int) $item->group_id); ?>">
                                                    <?php echo $this->escape($item->group_title); ?></a>
                                            <?php else : ?>
                                                <?php echo $this->escape($item->group_title); ?>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="text-body-secondary" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_GROUP_NONE')); ?>">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php if ($item->main) : ?>
                                            <div class="fw-bold fs-4 mb-1"><?php echo $catLink($item->main); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item->extra) : ?>
                                            <div class="small"><?php echo implode(', ', array_map($catLink, $item->extra)); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item->brand) : ?>
                                            <div class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_LIST_BRAND'); ?>: <?php echo $catLink($item->brand); ?></div>
                                        <?php endif; ?>
                                        <?php if ($item->badges) : ?>
                                            <div class="mt-1">
                                                <?php foreach ($item->badges as $badge) : ?>
                                                    <span class="badge bg-<?php echo $this->escape($badge->color); ?>"><?php echo $this->escape($badge->title); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell text-end">
                                        <?php
                                        // Скидка действует, если итоговая цена ниже обычной: старая цена зачёркнута, новая, размер скидки.
                                        $hasDiscount = (int) $item->variants_count === 1 && $item->price_final !== null && $item->price_regular !== null
                                            && (float) $item->price_final < (float) $item->price_regular && $item->discount !== null;
                                        ?>
                                        <?php if ($item->price_min === null) : ?>
                                            <span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_PRICE_ON_REQUEST'); ?></span>
                                        <?php elseif ($hasDiscount) : ?>
                                            <div class="fw-bold"><?php echo $money->format($item->price_final); ?></div>
                                            <div class="small text-nowrap">
                                                <del class="text-body-secondary"><?php echo $money->format($item->price_regular); ?></del>
                                                <span class="badge bg-danger"><?php echo $this->escape('−' . ((int) $item->discount_percent === 1 ? Discount::format($item->discount, 1) : $money->format($item->discount))); ?></span>
                                            </div>
                                        <?php elseif ($item->price_max !== null && (float) $item->price_max > (float) $item->price_min) : ?>
                                            <?php echo Text::sprintf('COM_DHARMASHOP_PRICE_FROM', $money->format($item->price_min)); ?>
                                        <?php else : ?>
                                            <?php echo $money->format($item->price_min); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell text-center">
                                        <?php if ((int) $item->in_stock === 1) : ?>
                                            <span class="badge bg-success">
                                                <?php echo Text::_('COM_DHARMASHOP_IN_STOCK'); ?><?php
                                                // У простого товара остаток берём из уже присоединённого варианта.
                                                // Без учёта остатков ноль означает отсутствие учёта, а не количество товара.
                                                if ((int) $item->variants_count === 1 && (int) $item->stock_tracked === 1 && $item->stock_qty !== null) {
                                                    $quantity = rtrim(rtrim(number_format((float) $item->stock_qty, 3, '.', ' '), '0'), '.');
                                                    echo ': ' . $this->escape($quantity);
                                                }
                                                ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="badge bg-secondary"><?php echo Text::_('COM_DHARMASHOP_OUT_OF_STOCK'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>

                    <?php // Окно пакетной обработки (открывается кнопкой в «Действиях»). ?>
                    <?php if ($user->authorise('core.create', 'com_dharmashop') && $user->authorise('core.edit', 'com_dharmashop')) : ?>
                        <template id="joomla-dialog-batch"><?php echo $this->loadTemplate('batch_body'); ?></template>
                    <?php endif; ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
