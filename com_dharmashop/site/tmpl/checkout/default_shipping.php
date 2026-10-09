<?php
/** Способ доставки: варианты выводят макеты плагинов типов (их можно переопределить в шаблоне сайта). */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if ($this->shippingOptions || $this->shippingBlocked) : ?>
<section class="card shadow-sm dharmaCheckoutShipping" aria-labelledby="dharma-checkout-shipping-title">
    <div class="card-body p-3">
        <h2 id="dharma-checkout-shipping-title" class="fs-6 fw-semibold mb-3"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SHIPPING'); ?></h2>
        <?php if ($this->shippingBlocked) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_DHARMASHOP_SHIPPING_NONE_FOR_ORDER'); ?></div>
        <?php else : ?>
            <?php // Способы — плитки-вкладки в ряд (выбранная выделена), под ними панель выбранного способа (макет плагина типа). ?>
            <div class="row row-cols-2 row-cols-md-3 g-2" role="radiogroup" aria-labelledby="dharma-checkout-shipping-title">
                <?php foreach ($this->shippingOptions as $option) : $this->shippingTile = $option; ?>
                    <?php echo $this->loadTemplate('shipping_tile'); ?>
                <?php endforeach; ?>
            </div>
            <div data-shipping-panels>
                <?php foreach ($this->shippingOptions as $option) : ?>
                    <?php echo $option['html']; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
