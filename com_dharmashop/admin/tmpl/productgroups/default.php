<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$user = $this->getCurrentUser();
$canEdit = $user->authorise('core.edit', 'com_dharmashop');
$canChange = $user->authorise('core.edit.state', 'com_dharmashop');
$catLink = function (object $entry) use ($user): string {
    $title = $this->escape($entry->title);
    if (!$user->authorise('core.edit', $entry->extension . '.category.' . $entry->id)) { return $title; }
    return '<a href="' . Route::_('index.php?option=com_dharmashop&task=category.edit&id=' . $entry->id . '&extension=' . $entry->extension) . '">' . $title . '</a>';
};
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroups'); ?>" method="post" name="adminForm" id="adminForm">
<?php echo LayoutHelper::render('joomla.searchtools.default',['view'=>$this]); ?>
<table class="table" id="productgroupList">
    <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_PRODUCTGROUPS_TITLE'); ?></caption>
    <thead>
        <tr>
            <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
            <th scope="col" class="w-5 text-center"><?php echo Text::_('JSTATUS'); ?></th>
            <th scope="col" class="w-1 text-center d-none d-md-table-cell"><span class="icon-image" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_HEADING_IMAGE'); ?></span></th>
            <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
            <th scope="col" class="w-20 d-none d-md-table-cell"><?php echo Text::_('COM_DHARMASHOP_HEADING_CATEGORIES'); ?></th>
            <th scope="col" class="w-30"><?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS'); ?></th>
            <th scope="col" class="w-5 d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($this->items as $i => $item) :
            $groupUrl = Route::_('index.php?option=com_dharmashop&task=productgroup.edit&id=' . (int) $item->id);
            $cover = trim(explode('#', (string) $item->cover, 2)[0]);
            ?>
            <tr>
                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'productgroups.', $canChange); ?></td>
                <td class="text-center d-none d-md-table-cell">
                    <?php if ($cover !== '') : ?>
                        <img src="<?php echo $this->escape(ImageUrl::resolve($cover, 'admin.group')); ?>" alt="" width="48" height="48" loading="lazy" class="rounded object-fit-cover">
                    <?php else : ?>
                        <span class="icon-image text-body-secondary" aria-hidden="true"></span>
                    <?php endif; ?>
                </td>
                <th scope="row" class="break-word">
                    <?php if ($canEdit) : ?>
                        <a href="<?php echo $groupUrl; ?>"><?php echo $this->escape($item->title); ?></a>
                    <?php else : ?>
                        <?php echo $this->escape($item->title); ?>
                    <?php endif; ?>
                    <div class="small break-word fw-normal text-body-secondary">
                        <?php echo Text::_('JFIELD_ALIAS_LABEL'); ?>: <?php echo $this->escape($item->alias); ?>
                    </div>
                </th>
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
                <td class="small break-word">
                    <?php if ($item->members) : ?>
                        <details>
                            <summary class="text-primary"><?php echo Text::sprintf('COM_DHARMASHOP_GROUP_MEMBERS_COUNT', (int) $item->members_count); ?></summary>
                            <ul class="list-unstyled mb-0 mt-2">
                                <?php foreach ($item->members as $member) :
                                    $canEditMember = $user->authorise('core.edit', 'com_dharmashop.product.' . $member->id)
                                        || ($user->authorise('core.edit.own', 'com_dharmashop.product.' . $member->id) && (int) $member->created_by === (int) $user->id);
                                    ?>
                                    <li class="mb-1">
                                        <?php if ($canEditMember) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_dharmashop&task=product.edit&id=' . (int) $member->id); ?>"><?php echo $this->escape($member->title); ?></a>
                                        <?php else : ?>
                                            <?php echo $this->escape($member->title); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ((int) $item->members_count > count($item->members)) : ?>
                                <div class="mt-2">
                                    <?php if ($canEdit) : ?><a href="<?php echo $groupUrl; ?>"><?php endif; ?>
                                    <?php echo Text::sprintf('COM_DHARMASHOP_GROUP_MEMBERS_REMAINING', (int) $item->members_count - count($item->members)); ?>
                                    <?php if ($canEdit) : ?></a><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php else : ?>
                        <span class="text-body-secondary">0</span>
                    <?php endif; ?>
                </td>
                <td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php echo $this->pagination->getListFooter(); ?>
<input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
