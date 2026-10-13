<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$order = $this->item;
?>
<div id="dharma_shop" class="dharmaOrder fs-6">
    <section class="py-4">
        <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="fs-4 mb-1"><?php echo Text::sprintf('COM_DHARMASHOP_ORDER_HEADING', $this->escape($order->number)); ?></h1>
                <p class="small text-body-secondary mb-0"><?php echo $this->escape($this->details->date); ?></p>
            </div>
            <?php if ($order->status_title) : ?><span class="badge bg-light text-dark border fw-normal"><?php echo $this->escape($order->status_title); ?></span><?php endif; ?>
        </header>
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-8">
                <?php echo $this->loadTemplate('items'); ?>
                <?php echo $this->loadTemplate('contacts'); ?>
                <?php echo $this->loadTemplate('shipping'); ?>
                <?php echo $this->loadTemplate('payment'); ?>
            </div>
            <div class="col-12 col-lg-4"><?php echo $this->loadTemplate('summary'); ?></div>
        </div>
        <?php if ($this->claimable) : ?>
            <form class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4" method="post" action="<?php echo $this->escape(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&task=order.claim')); ?>">
                <span><?php echo Text::_('COM_DHARMASHOP_ORDER_CLAIM_HINT'); ?></span>
                <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
                <input type="hidden" name="key" value="<?php echo $this->escape($this->claimKey); ?>">
                <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
                <button class="btn btn-primary" type="submit"><?php echo Text::_('COM_DHARMASHOP_ORDER_CLAIM'); ?></button>
            </form>
        <?php elseif ($this->claimNeedsLogin) : ?>
            <p class="small text-body-secondary mt-4 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_CLAIM_LOGIN'); ?></p>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary mt-4" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
    </section>
</div>
