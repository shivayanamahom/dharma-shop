<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('table.columns');
$state = $this->getModel()->getState();
$dir = (string) $state->get('list.direction'); $sort = (string) $state->get('list.ordering');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=orders'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default',['view'=>$this]); ?>
        <?php if (!$this->items) : ?><div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
        <?php else : ?><table class="table" id="orderList"><caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_MENU_ORDERS'); ?></caption>
            <thead><tr><th scope="col"><?php echo HTMLHelper::_('searchtools.sort','COM_DHARMASHOP_ORDER_NUMBER','a.number',$dir,$sort); ?></th><th scope="col"><?php echo HTMLHelper::_('searchtools.sort','JDATE','a.created',$dir,$sort); ?></th><th scope="col"><?php echo Text::_('COM_DHARMASHOP_ORDER_RECIPIENT'); ?></th><th scope="col"><?php echo HTMLHelper::_('searchtools.sort','JSTATUS','a.status_id',$dir,$sort); ?></th><th scope="col"><?php echo Text::_('COM_DHARMASHOP_ORDER_ITEMS'); ?></th><th scope="col"><?php echo HTMLHelper::_('searchtools.sort','COM_DHARMASHOP_ORDER_TOTAL','a.total',$dir,$sort); ?></th></tr></thead>
            <tbody><?php foreach ($this->items as $item) : $color = in_array($item->status_color,['primary','secondary','success','danger','warning','info','light','dark'],true) ? $item->status_color : 'secondary'; ?>
                <tr><th scope="row"><a href="<?php echo Route::_('index.php?option=com_dharmashop&view=order&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->number ?: '#' . $item->id); ?></a></th>
                    <td><?php echo HTMLHelper::_('date',$item->created,Text::_('DATE_FORMAT_LC6')); ?></td><td><div><?php echo $this->escape($item->contact_name); ?></div><div class="small text-body-secondary"><?php echo $this->escape($item->contact_email); ?></div><div class="small text-body-secondary"><?php echo $this->escape($item->contact_phone); ?></div></td>
                    <td><span class="badge text-bg-<?php echo $color; ?>"><?php echo $this->escape($item->status_title); ?></span></td><td><?php echo (int) $item->items_count; ?></td><td class="text-nowrap"><?php echo $this->escape(number_format((float) $item->total,2,',',' ') . ' ' . $item->currency); ?></td>
                </tr><?php endforeach; ?></tbody>
        </table><?php echo $this->pagination->getListFooter(); ?><?php endif; ?>
        <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
