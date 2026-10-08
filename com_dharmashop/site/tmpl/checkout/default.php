<?php
/** Оболочка checkout: отдельный макет формы подключает контакты, согласия и состав заказа. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaCheckout fs-6">
    <section class="py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h1 class="fs-4 mb-0"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_TITLE'); ?></h1>
            <?php if ($this->lines) : ?><span class="badge text-bg-light fw-normal"><?php echo Text::sprintf('COM_DHARMASHOP_CHECKOUT_ITEMS_COUNT', count($this->lines)); ?></span><?php endif; ?>
        </div>
        <?php if ($this->receipt) : ?>
            <?php echo $this->loadTemplate('success'); ?>
        <?php elseif ($this->loginRequired) : ?>
            <div class="card shadow-sm"><div class="card-body p-4"><p><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN_REQUIRED'); ?></p><a class="btn btn-primary bg-primary text-white" href="<?php echo $this->escape($this->loginUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN'); ?></a></div></div>
        <?php else : ?>
            <?php echo $this->loadTemplate('form'); ?>
        <?php endif; ?>
    </section>
</div>
