<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=customer&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="customer-form" class="form-validate">
    <div class="row g-4">
        <div class="col-lg-8"><div class="main-card p-4"><fieldset class="options-form"><legend><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_CONTACTS'); ?></legend><?php echo $this->form->renderFieldset('contacts'); ?></fieldset></div></div>
        <div class="col-lg-4"><fieldset class="options-form"><legend><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_ACCOUNT'); ?></legend>
            <dl class="row mb-0"><dt class="col-5">ID</dt><dd class="col-7"><?php echo (int) $this->item->id; ?></dd>
                <dt class="col-5"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_ACCOUNT'); ?></dt><dd class="col-7 text-break"><?php echo $this->escape($this->username ?: ($this->item->user_id ? '#' . (int) $this->item->user_id : Text::_('COM_DHARMASHOP_CUSTOMER_GUEST'))); ?></dd>
                <?php if ($this->item->created) : ?><dt class="col-5"><?php echo Text::_('JDATE'); ?></dt><dd class="col-7"><?php echo HTMLHelper::_('date',$this->item->created,Text::_('DATE_FORMAT_LC6')); ?></dd><?php endif; ?>
            </dl><p class="small text-body-secondary mb-0"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_SNAPSHOT_HINT'); ?></p>
        </fieldset></div>
    </div>
    <?php if ($this->item->id && $this->getCurrentUser()->authorise('dharmashop.orders.manage','com_dharmashop')) : ?>
        <div class="main-card mt-4 p-4"><h2 class="fs-5"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_RECENT_ORDERS'); ?></h2>
            <?php if (!$this->orders) : ?><p class="mb-0 text-body-secondary"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p>
            <?php else : ?><div class="table-responsive"><table class="table"><thead><tr><th><?php echo Text::_('COM_DHARMASHOP_ORDER_NUMBER'); ?></th><th><?php echo Text::_('JDATE'); ?></th><th><?php echo Text::_('JSTATUS'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_ORDER_TOTAL'); ?></th></tr></thead><tbody>
                <?php foreach ($this->orders as $order) : ?><tr><td><a href="<?php echo Route::_('index.php?option=com_dharmashop&view=order&id=' . (int) $order->id); ?>"><?php echo $this->escape($order->number); ?></a></td><td><?php echo HTMLHelper::_('date',$order->created,Text::_('DATE_FORMAT_LC6')); ?></td><td><?php echo $this->escape($order->status_title); ?></td><td><?php echo $this->escape(number_format((float) $order->total,2,',',' ') . ' ' . $order->currency); ?></td></tr><?php endforeach; ?>
            </tbody></table></div><a href="<?php echo Route::_('index.php?option=com_dharmashop&view=orders&filter[customer_id]=' . (int) $this->item->id); ?>"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_ALL_ORDERS'); ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
    <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
</form>
