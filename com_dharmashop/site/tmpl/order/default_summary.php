<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$order = $this->item;
$money = $this->details->money;
?>
<section class="card shadow-sm dharmaOrderSummary">
    <div class="card-header bg-body-tertiary py-3"><h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_SUMMARY'); ?></h2></div>
    <div class="card-body">
        <dl class="mb-0">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <dt class="small text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_SUBTOTAL'); ?></dt>
                <dd class="small mb-0"><?php echo $this->escape($this->details->subtotal); ?></dd>
            </div>
            <?php foreach ($order->adjustments as $adjustment) : ?>
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <dt class="small text-body-secondary fw-normal"><?php echo $this->escape($adjustment->title); ?></dt>
                    <dd class="small mb-0"><?php echo $this->escape($money->format($adjustment->amount)); ?></dd>
                </div>
            <?php endforeach; ?>
            <?php foreach (['shipping_title' => 'COM_DHARMASHOP_ORDER_SHIPPING', 'payment_title' => 'COM_DHARMASHOP_ORDER_PAYMENT'] as $key => $label) : if (trim((string) $order->$key) === '') continue; ?>
                <div class="mb-3"><dt class="small text-body-secondary fw-normal"><?php echo Text::_($label); ?></dt><dd class="small mb-0"><?php echo $this->escape($order->$key); ?></dd></div>
            <?php endforeach; ?>
            <div class="border-top pt-3">
                <dt class="small fw-normal mb-1"><?php echo Text::_('COM_DHARMASHOP_ORDER_TOTAL'); ?></dt>
                <dd class="fs-5 fw-semibold mb-0"><?php echo $this->escape($this->details->total); ?></dd>
            </div>
        </dl>
    </div>
</section>
