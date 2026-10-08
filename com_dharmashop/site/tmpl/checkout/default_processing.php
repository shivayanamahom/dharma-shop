<?php
/** Индикатор ожидания штатного POST: без фиктивных процентов или этапов сервера. */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div class="modal dharmaCheckoutProcessing" id="dharma-checkout-processing" tabindex="-1" aria-labelledby="dharma-checkout-processing-title" aria-describedby="dharma-checkout-processing-description" aria-hidden="true" data-checkout-processing>
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-body p-4 text-center" role="status" aria-live="polite" aria-atomic="true">
                <div class="text-primary mb-3" aria-hidden="true"><span class="spinner-border"></span></div>
                <h2 class="fs-5 fw-semibold mb-2" id="dharma-checkout-processing-title"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_PROCESSING'); ?></h2>
                <p class="small text-body-secondary mb-4" id="dharma-checkout-processing-description"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_PROCESSING_DESC'); ?></p>
                <div class="progress rounded-pill" role="progressbar" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CHECKOUT_PROCESSING')); ?>">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                </div>
                <p class="small text-body-secondary mt-3 mb-0 d-none" data-checkout-processing-slow><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_PROCESSING_SLOW'); ?></p>
            </div>
        </div>
    </div>
</div>
