<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
$this->getDocument()->getWebAssetManager()->useScript('core')->useScript('modal-content-select');
$state = $this->getModel()->getState();
$order = $this->escape((string) $state->get('list.ordering'));
$direction = $this->escape((string) $state->get('list.direction'));
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroups&layout=modal&tmpl=component&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (!$this->items) : ?>
            <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php else : ?>
            <table class="table table-sm align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_GROUP_PICKER_SELECT'); ?></caption>
                <thead><tr>
                    <th scope="col" class="w-1"><?php echo Text::_('COM_DHARMASHOP_HEADING_IMAGE'); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'g.title', $direction, $order); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_HEADING_PRICE'); ?></th>
                    <th scope="col" class="w-1"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'g.id', $direction, $order); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($this->items as $item) : ?>
                        <tr>
                            <td>
                                <?php if ($item->picker_image !== '') : ?>
                                    <img src="<?php echo $this->escape($item->picker_image); ?>" alt="" width="48" height="48" class="rounded object-fit-cover" loading="lazy" decoding="async">
                                <?php else : ?><span class="icon-image text-body-secondary" aria-hidden="true"></span><?php endif; ?>
                            </td>
                            <th scope="row">
                                <button type="button" class="btn btn-link text-start p-0" data-content-select data-content-type="com_dharmashop.productgroup" data-id="<?php echo (int) $item->id; ?>" data-title="<?php echo $this->escape($item->title); ?>"><?php echo $this->escape($item->title); ?></button>
                                <div class="small text-body-secondary"><?php echo $this->escape($item->alias); ?></div>
                                <?php if (!(int) $item->state) : ?><span class="badge bg-secondary"><?php echo Text::_('JUNPUBLISHED'); ?></span><?php endif; ?>
                            </th>
                            <td class="text-end"><?php echo (int) $item->members_count; ?></td>
                            <td class="text-end"><?php echo $this->escape($item->picker_price); ?></td>
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
    </form>
</div>
