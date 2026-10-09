<?php
/** Способ оплаты: плитки-вкладки в ряд (как у доставки), под ними панель выбранного способа (макет плагина типа). Переопределяется в шаблоне сайта. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if ($this->paymentOptions || $this->paymentBlocked) : ?>
<section class="card shadow-sm dharmaCheckoutPayment" aria-labelledby="dharma-checkout-payment-title" data-payment-map="<?php echo $this->escape($this->paymentMap); ?>">
    <div class="card-body p-3">
        <h2 id="dharma-checkout-payment-title" class="fs-6 fw-semibold mb-3"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_PAYMENT'); ?></h2>
        <?php if ($this->paymentBlocked) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_DHARMASHOP_PAYMENT_NONE_FOR_ORDER'); ?></div>
        <?php else : ?>
            <div class="row row-cols-2 row-cols-md-3 g-2" role="radiogroup" aria-labelledby="dharma-checkout-payment-title">
                <?php foreach ($this->paymentOptions as $option) : $this->paymentTile = $option; ?>
                    <?php echo $this->loadTemplate('payment_tile'); ?>
                <?php endforeach; ?>
            </div>
            <div class="alert alert-warning mt-2 mb-0<?php echo array_filter($this->paymentOptions, static fn (array $option): bool => $option['visible']) ? ' d-none' : ''; ?>" data-payment-none><?php echo Text::_('COM_DHARMASHOP_PAYMENT_NONE_FOR_ORDER'); ?></div>
            <div data-payment-panels>
                <?php foreach ($this->paymentOptions as $option) : ?>
                    <?php echo $option['html']; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
