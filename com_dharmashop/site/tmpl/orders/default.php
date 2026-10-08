<?php
/**
 * Мои заказы: список заказов вошедшего пользователя (номер, дата, статус, число позиций, сумма, ссылка на заказ). Гостю — приглашение
 * войти. Данные готовит вид (`$this->orders`), SQL здесь нет. Только классы Bootstrap.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaOrders">
<section class="py-4">
    <h1 class="mb-4"><?php echo Text::_('COM_DHARMASHOP_MYORDERS'); ?></h1>
    <?php if ($this->guest) : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_MYORDERS_LOGIN'); ?></p>
        <a class="btn btn-outline-primary" href="<?php echo $this->escape($this->loginUrl); ?>"><?php echo Text::_('JLOGIN'); ?></a>
    <?php elseif (!$this->orders) : ?>
        <p class="text-body-secondary dharmaOrdersEmpty"><?php echo Text::_('COM_DHARMASHOP_MYORDERS_EMPTY'); ?></p>
        <a class="btn btn-outline-primary" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
    <?php else : ?>
        <div class="list-group list-group-flush border-top border-bottom dharmaOrdersList">
            <?php foreach ($this->orders as $order) : ?>
                <a class="list-group-item list-group-item-action py-3 dharmaOrdersItem" href="<?php echo $this->escape($order->url); ?>">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-4">
                            <div class="fw-semibold"><?php echo Text::sprintf('COM_DHARMASHOP_ORDER_HEADING', $this->escape($order->number)); ?></div>
                            <div class="small text-body-secondary"><?php echo $this->escape($order->date); ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <?php if ((string) $order->status_title !== '') : ?><span class="badge text-bg-<?php echo $this->escape($order->color); ?> fw-normal"><?php echo $this->escape($order->status_title); ?></span><?php endif; ?>
                        </div>
                        <div class="col-6 col-md-2 small text-body-secondary text-md-center"><?php echo $this->escape(Text::plural('COM_DHARMASHOP_MYORDERS_ITEMS', (int) $order->items_count)); ?></div>
                        <div class="col-12 col-md-3 text-md-end fw-bold"><?php echo $this->escape($order->amount); ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($this->pagination) : ?><div class="mt-4"><?php echo $this->pagination->getPaginationLinks(); ?></div><?php endif; ?>
    <?php endif; ?>
</section>
</div>
