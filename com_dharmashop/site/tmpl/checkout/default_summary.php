<?php
/** Состав, серверный расчёт и кнопка оформления. Доставка/оплата подключаются далее. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$discount = $this->discountTotals();
$qtyText = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
?>
<aside class="card shadow-sm dharmaCheckoutSummary" aria-labelledby="dharma-checkout-summary-title">
    <div class="card-header bg-body-tertiary p-3">
        <h2 id="dharma-checkout-summary-title" class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SUMMARY'); ?></h2>
    </div>
    <div class="card-body p-3 small">
        <?php // Недоступные позиции: кнопки отправляют отдельную форму `dharma-cart-fix` (вложенных форм нет). ?>
        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.unavailable', null, ['component' => 'none', 'client' => 0]))->render(['lines' => $this->lines, 'formId' => 'dharma-cart-fix']); ?>
        <?php if (!$this->lines) : ?>
            <p class="text-body-secondary mb-0"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
        <?php else : ?>
            <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.promotion-progress', null, ['component' => 'none', 'client' => 0]))->render(['progress' => $this->totals['promotion_progress'] ?? [], 'money' => $this->money]); ?>
            <?php if (!empty($this->totals['promotion_input_available']) || !empty($this->totals['applied_code'])) : ?>
            <div class="border-top mt-3 pt-3 mb-3">
                <label class="form-label" for="dharma-checkout-promotion-code"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_CODE'); ?></label>
                <div class="input-group">
                    <input type="text" id="dharma-checkout-promotion-code" class="form-control" name="promotion_code" value="<?php echo $this->escape($this->totals['applied_code'] ?? ''); ?>" maxlength="64" autocomplete="off">
                    <button type="submit" class="btn btn-outline-primary" name="promotion_action" value="apply" formnovalidate><?php echo Text::_('COM_DHARMASHOP_PROMOTION_APPLY'); ?></button>
                </div>
                <?php if (!empty($this->totals['promotion_error'])) : ?><div class="alert alert-warning small mt-2 mb-2" role="alert"><?php echo $this->escape(Text::_($this->totals['promotion_error'])); ?></div><?php endif; ?>
                <?php if (!empty($this->totals['promotion_pending_identity'])) : ?><div class="form-text"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_IDENTITY_PENDING'); ?></div><?php endif; ?>
                <?php if (!empty($this->totals['applied_description'])) : ?><div class="form-text"><?php echo $this->escape($this->totals['applied_description']); ?></div><?php endif; ?>
                <?php if (!empty($this->totals['applied_code'])) : ?><button type="submit" class="btn btn-sm btn-link px-0" name="promotion_action" value="remove" formnovalidate><?php echo Text::_('COM_DHARMASHOP_PROMOTION_REMOVE'); ?></button><?php endif; ?>
                <div class="form-text"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_CHECKOUT_HELP'); ?></div>
            </div>
            <?php endif; ?>
            <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                <?php foreach ($this->lines as $line) : ?>
                    <li>
                        <div class="d-flex justify-content-between align-items-baseline gap-3">
                            <a class="fw-semibold text-break link-body-emphasis text-decoration-none" href="<?php echo $this->escape($line->url); ?>"><?php echo $this->escape($line->title); ?></a>
                            <?php if ($line->error === '') : ?><span class="fw-semibold text-end flex-shrink-0"><?php echo $this->escape($this->money->price((string) $line->total, !empty($line->zero_free))); ?></span><?php endif; ?>
                        </div>
                        <?php if ($line->variant_title) : ?><div class="text-body-secondary"><?php echo $this->escape($line->variant_title); ?></div><?php endif; ?>
                        <?php if ($line->error !== '') : ?>
                            <div class="text-danger"><?php echo $this->escape(Text::_($line->error)); ?></div>
                        <?php else : ?>
                            <div class="text-body-secondary"><?php echo $this->escape($qtyText((float) $line->qty)); ?> × <?php echo $this->escape($this->money->price((string) $line->unit_price, !empty($line->zero_free))); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($discount['saved'] > 0 || $this->totals['adjustments'] || $this->shippingOptions) : ?>
                <div class="border-top mt-3 pt-3 d-flex flex-column gap-2">
                    <?php if ($discount['saved'] > 0) : ?>
                        <div class="d-flex justify-content-between gap-3"><span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CART_BEFORE_DISCOUNT'); ?></span><span class="text-end"><?php echo $this->escape($this->money->format((string) $discount['before'])); ?></span></div>
                        <div class="d-flex justify-content-between gap-3 text-success"><span><?php echo Text::_('COM_DHARMASHOP_CART_DISCOUNT'); ?></span><span class="text-end">−<?php echo $this->escape($this->money->format((string) $discount['saved'])); ?></span></div>
                    <?php endif; ?>
                    <?php foreach ($this->totals['adjustments'] as $adjustment) : ?>
                        <div class="d-flex justify-content-between gap-3"><span><?php echo $this->escape($adjustment['title']); ?></span><span class="text-end"><?php echo $this->escape($this->money->format((string) $adjustment['amount'])); ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($this->shippingOptions) : ?>
                        <div class="d-flex justify-content-between gap-3"><span><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SHIPPING_ROW'); ?></span><span class="text-end" data-checkout-shipping-price><?php echo $this->escape($this->shippingPending ? Text::_('COM_DHARMASHOP_SHIPPING_PENDING') : ($this->shippingPrice > 0 ? $this->money->format((string) $this->shippingPrice) : Text::_('COM_DHARMASHOP_SHIPPING_FREE'))); ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($this->shippingOptions) : ?>
                <?php // Сведения выбранного способа доставки (из плагина типа); боковая панель переключает блоки и добавляет введённые покупателем значения. ?>
                <div class="border-top mt-3 pt-3 dharmaCheckoutShippingSummary" data-checkout-shipping-summary>
                    <div class="text-body-secondary mb-1"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SHIPPING'); ?></div>
                    <?php foreach ($this->shippingOptions as $optionId => $option) : ?>
                        <div<?php echo $optionId === $this->shippingSelected ? '' : ' class="d-none"'; ?> data-shipping-details="<?php echo (int) $optionId; ?>">
                            <div class="fw-semibold text-break"><?php echo $this->escape($option['title']); ?></div>
                            <?php if ($option['term'] !== '') : ?><div class="text-body-secondary"><?php echo $this->escape($option['term']); ?></div><?php endif; ?>
                            <?php foreach ($option['info'] as $row) : ?>
                                <div class="text-break"><span class="text-body-secondary"><?php echo $this->escape($row['label']); ?>:</span> <?php echo nl2br($this->escape($row['value'])); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div data-checkout-shipping-input></div>
                </div>
            <?php endif; ?>
            <?php if ($this->paymentOptions) : ?>
                <?php // Выбранный способ оплаты и его сведения (из плагина типа); боковая панель переключает блоки по выбору. ?>
                <div class="border-top mt-3 pt-3 dharmaCheckoutPaymentSummary" data-checkout-payment-summary>
                    <div class="text-body-secondary mb-1"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_PAYMENT'); ?></div>
                    <?php foreach ($this->paymentOptions as $optionId => $option) : ?>
                        <div<?php echo $optionId === $this->paymentSelected ? '' : ' class="d-none"'; ?> data-payment-details="<?php echo (int) $optionId; ?>">
                            <div class="fw-semibold text-break"><?php echo $this->escape($option['title']); ?></div>
                            <?php foreach ($option['info'] as $row) : ?>
                                <div class="text-break"><span class="text-body-secondary"><?php echo $this->escape($row['label']); ?>:</span> <?php echo nl2br($this->escape($row['value'])); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div data-checkout-payment-input></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-body p-3">
        <?php if ($this->lines) : ?>
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
                <span class="small fw-semibold"><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL_SHORT'); ?></span>
                <span class="fs-5 fw-semibold text-end text-break" data-checkout-total><?php echo $this->escape($this->totalText($this->totalWithShipping())); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($this->lines && !$this->shippingBlocked && !$this->paymentBlocked && !array_filter($this->lines, static fn (object $line): bool => $line->error !== '')) : ?>
            <button type="submit" class="btn btn-primary bg-primary text-white w-100 mb-2" data-checkout-submit><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SUBMIT'); ?></button>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo $this->escape($this->cartUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_BACK_TO_CART'); ?></a>
    </div>
</aside>
