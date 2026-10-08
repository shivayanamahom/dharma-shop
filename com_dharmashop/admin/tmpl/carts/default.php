<?php

/**
 * Dharma Shop — раздел «Корзины»: гости и пользователи, состав, сумма по текущим ценам, срок, секретный ключ (токен) и ссылка для просмотра
 * корзины на сайте (только для администратора, только чтение). Нативный список Joomla (searchtools, пагинация).
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Carts\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$state = $this->getModel()->getState();
$listOrder = $this->escape((string) $state->get('list.ordering'));
$listDirn = $this->escape((string) $state->get('list.direction'));
$now = Factory::getDate()->toSql();
$root = rtrim(Uri::root(), '/');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=carts'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (!$this->items) : ?>
            <div class="alert alert-info"><span class="icon-info-circle" aria-hidden="true"></span> <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php else : ?>
            <table class="table" id="cartList">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_MENU_CARTS'); ?></caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col" class="w-1"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'c.id', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CARTS_OWNER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CARTS_CONTENTS'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_CARTS_TOTAL'); ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_CARTS_MODIFIED', 'c.modified', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_CARTS_EXPIRES', 'c.expires', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="d-none d-xl-table-cell"><?php echo Text::_('COM_DHARMASHOP_CARTS_SECRET'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <?php
                        $expired = (string) $item->expires <= $now;
                        $forever = (string) $item->expires >= '2900-01-01';
                        $siteUrl = $root . '/index.php?option=com_dharmashop&view=cart&cart=' . rawurlencode((string) $item->token);
                        ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', (string) $item->id); ?></td>
                            <td><?php echo (int) $item->id; ?></td>
                            <td>
                                <?php if ($item->user_id) : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item->user_id); ?>"><?php echo $this->escape((string) ($item->user_name ?: $item->user_username)); ?></a>
                                    <div class="small text-body-secondary"><?php echo $this->escape((string) $item->user_email); ?></div>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_GUEST'); ?></span>
                                    <?php if ($item->customer_id) : ?><div class="small text-body-secondary"><?php echo $this->escape((string) ($item->customer_name ?: $item->customer_email)); ?></div><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$item->lines_count) : ?>
                                    <span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CARTS_EMPTY'); ?></span>
                                <?php else : ?>
                                    <div class="small mb-1"><?php echo Text::sprintf('COM_DHARMASHOP_CARTS_SUMMARY', (int) $item->lines_count, rtrim(rtrim(number_format($item->qty_sum, 3, '.', ''), '0'), '.')); ?></div>
                                    <ul class="list-unstyled small mb-0">
                                        <?php foreach (array_slice($item->lines, 0, 4) as $line) : ?>
                                            <li><?php echo $this->escape((string) $line->title); ?> <span class="text-body-secondary">× <?php echo $this->escape(rtrim(rtrim(number_format((float) $line->qty, 3, '.', ''), '0'), '.')); ?></span></li>
                                        <?php endforeach; ?>
                                        <?php if (count($item->lines) > 4) : ?><li class="text-body-secondary">… +<?php echo count($item->lines) - 4; ?></li><?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap"><?php echo $item->lines_count ? $this->escape($this->money->format((string) $item->total_sum)) : '—'; ?><?php if ($item->no_price) : ?><div class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CARTS_NO_PRICE'); ?></div><?php endif; ?></td>
                            <td class="d-none d-lg-table-cell small"><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC6')); ?></td>
                            <td class="d-none d-lg-table-cell small">
                                <?php if ($forever) : ?>
                                    <?php echo Text::_('COM_DHARMASHOP_CARTS_NO_EXPIRY'); ?>
                                <?php else : ?>
                                    <?php echo HTMLHelper::_('date', $item->expires, Text::_('DATE_FORMAT_LC6')); ?>
                                    <?php if ($expired) : ?><span class="badge bg-warning text-dark"><?php echo Text::_('COM_DHARMASHOP_CARTS_STATE_EXPIRED_BADGE'); ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-xl-table-cell small">
                                <code class="user-select-all"><?php echo $this->escape((string) $item->token); ?></code>
                                <?php if (!$expired) : ?><div><a href="<?php echo $this->escape($siteUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_DHARMASHOP_CARTS_OPEN_ON_SITE'); ?></a></div><?php endif; ?>
                            </td>
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
