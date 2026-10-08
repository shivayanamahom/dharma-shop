<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('table.columns');
$state = $this->getModel()->getState();
$direction = (string) $state->get('list.direction'); $order = (string) $state->get('list.ordering');
$edit = $this->getCurrentUser()->authorise('core.edit', 'com_dharmashop');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=customers'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view'=>$this]); ?>
        <?php if (!$this->items) : ?><div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php else : ?>
        <table class="table" id="customerList">
            <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_MENU_CUSTOMERS'); ?></caption>
            <thead><tr>
                <th scope="col"><?php echo HTMLHelper::_('searchtools.sort','COM_DHARMASHOP_CUSTOMER_NAME','a.name',$direction,$order); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_CONTACTS'); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_ACCOUNT'); ?></th>
                <th scope="col"><?php echo Text::_('COM_DHARMASHOP_MENU_ORDERS'); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('searchtools.sort','JDATE','a.created',$direction,$order); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('searchtools.sort','JGRID_HEADING_ID','a.id',$direction,$order); ?></th>
            </tr></thead><tbody>
            <?php foreach ($this->items as $item) : ?>
                <tr><th scope="row"><a href="<?php echo Route::_('index.php?option=com_dharmashop&' . ($edit ? 'task=customer.edit' : 'view=customer&layout=edit') . '&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->name ?: Text::sprintf('COM_DHARMASHOP_CUSTOMER_NUMBER', $item->id)); ?></a></th>
                    <td><div><?php echo $this->escape($item->email); ?></div><div class="small text-body-secondary"><?php echo $this->escape($item->phone); ?></div></td>
                    <td><?php echo $item->user_id ? $this->escape($item->username ?: '#' . (int) $item->user_id) : Text::_('COM_DHARMASHOP_CUSTOMER_GUEST'); ?></td>
                    <td><?php if ($this->getCurrentUser()->authorise('dharmashop.orders.manage', 'com_dharmashop')) : ?><a href="<?php echo Route::_('index.php?option=com_dharmashop&view=orders&filter[customer_id]=' . (int) $item->id); ?>"><?php echo (int) $item->orders_count; ?></a><?php else : ?><?php echo (int) $item->orders_count; ?><?php endif; ?></td>
                    <td><?php echo HTMLHelper::_('date',$item->created,Text::_('DATE_FORMAT_LC6')); ?></td><td><?php echo (int) $item->id; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table><?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
        <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
