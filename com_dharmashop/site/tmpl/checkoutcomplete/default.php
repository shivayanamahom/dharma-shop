<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaCheckoutComplete fs-6">
    <section class="py-4">
        <div class="card shadow-sm dharmaCheckoutSuccess">
            <div class="card-body p-4">
                <?php if ($this->order) : ?>
                    <div class="row g-4 align-items-center">
                        <div class="col-12 col-lg-8">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="d-inline-flex rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>
                                </span>
                                <h1 class="fs-4 mb-0"><?php echo Text::sprintf('COM_DHARMASHOP_CHECKOUT_SUCCESS', $this->escape($this->order->number)); ?></h1>
                            </div>
                            <p class="text-body-secondary mb-4"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SUCCESS_TEXT'); ?></p>
                            <?php // Оплата: способ и сведения из снимка (инструкция), кнопка — если платёж ещё не завершён. ?>
                            <?php
                            $payment = (array) ($this->order->payment ?? []);
                            $payRows = array_filter((array) ($payment['rows'] ?? []), static fn ($row) => is_array($row) && trim((string) ($row['value'] ?? '')) !== '');
                            $payStatus = (string) ($this->order->payment_status ?? 'none');
                            ?>
                            <?php if (!empty($payment['title'])) : ?>
                                <div class="bg-body-tertiary rounded p-3 mb-4 small dharmaCheckoutPayment">
                                    <div class="fw-semibold mb-1"><?php echo Text::_('COM_DHARMASHOP_ORDER_PAYMENT'); ?>: <?php echo $this->escape((string) $payment['title']); ?></div>
                                    <?php foreach ($payRows as $row) : ?><div class="text-break"><?php echo nl2br($this->escape((string) $row['value'])); ?></div><?php endforeach; ?>
                                    <?php if (in_array($payStatus, ['pending', 'failed', 'cancelled'], true)) : ?>
                                        <a class="btn btn-primary bg-primary text-white btn-sm mt-2" href="<?php echo $this->escape(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=payment&id=' . (int) $this->order->id, false)); ?>"><?php echo Text::_('COM_DHARMASHOP_PAYMENT_PAY_NOW'); ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo $this->escape($this->orderUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_ORDER_OPEN'); ?></a>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <dl class="bg-body-tertiary rounded p-3 mb-0 small dharmaCheckoutReceipt">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                    <dt class="text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_DATE'); ?></dt>
                                    <dd class="mb-0"><?php echo $this->escape($this->details->date); ?></dd>
                                </div>
                                <?php if ($this->order->status_title) : ?>
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                        <dt class="text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_STATUS'); ?></dt>
                                        <dd class="mb-0"><?php echo $this->escape($this->order->status_title); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <div class="border-top pt-2">
                                    <dt class="text-body-secondary fw-normal mb-1"><?php echo Text::_('COM_DHARMASHOP_ORDER_TOTAL'); ?></dt>
                                    <dd class="fs-6 fw-semibold mb-0"><?php echo $this->escape($this->details->total); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                <?php else : ?>
                    <h1 class="fs-4 mb-3"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_COMPLETE_TITLE'); ?></h1>
                    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_COMPLETE_EMPTY'); ?></p>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->escape($this->cartUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_BACK_TO_CART'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
