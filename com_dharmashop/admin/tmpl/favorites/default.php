<?php
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Favorites\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=favorites'); ?>" method="get" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_dharmashop">
    <input type="hidden" name="view" value="favorites">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <label class="visually-hidden" for="filter-search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
        <input id="filter-search" type="search" class="form-control w-auto" name="filter_search" value="<?php echo $this->escape($this->search); ?>" placeholder="<?php echo $this->escape(Text::_('COM_DHARMASHOP_FAVORITES_SEARCH')); ?>">
        <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
        <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_dharmashop&view=favorites'); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
    </div>
    <?php if (!$this->items) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_ADMIN_EMPTY'); ?></div>
    <?php else : ?>
        <table class="table table-striped">
            <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_MENU_FAVORITES'); ?></caption>
            <thead><tr>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_OWNER'); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_PRODUCTS'); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_COUNT'); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_MODIFIED'); ?></th>
            </tr></thead>
            <tbody><?php foreach ($this->items as $item) : ?>
                <tr>
                    <td><?php if ($item->user_id) : ?><a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item->user_id); ?>"><?php echo $this->escape((string) ($item->user_name ?: $item->username)); ?></a><div class="small text-body-secondary"><?php echo $this->escape((string) $item->email); ?></div><?php else : ?><span class="badge text-bg-secondary"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_GUEST'); ?></span><?php endif; ?></td>
                    <td><ul class="list-unstyled mb-0 small"><?php foreach (array_slice($item->product_titles, 0, 8) as $title) : ?><li><?php echo $this->escape($title); ?></li><?php endforeach; ?><?php if (count($item->product_titles) > 8) : ?><li class="text-body-secondary">… +<?php echo count($item->product_titles) - 8; ?></li><?php endif; ?></ul></td>
                    <td><?php echo (int) $item->items_count; ?></td>
                    <td><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC6')); ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>
</form>
