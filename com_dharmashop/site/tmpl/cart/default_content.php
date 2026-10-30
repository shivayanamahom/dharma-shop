<?php
/**
 * Содержимое страницы корзины: пустая корзина или таблица позиций с итогами. Подгружается и целиком заменяется скриптом cart-page.js
 * после каждого действия (фрагмент `layout=page&format=raw`). Только классы Bootstrap; data-атрибуты — для скрипта; без JavaScript
 * работают формы (удаление, очистка и скрытая в браузере с JavaScript кнопка «Пересчитать»).
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$remove = Route::_('index.php?option=com_dharmashop&task=cart.remove', false);
$clear = Route::_('index.php?option=com_dharmashop&task=cart.clear', false);
$applyPromotion = Route::_('index.php?option=com_dharmashop&task=cart.applyPromotion', false);
$removePromotion = Route::_('index.php?option=com_dharmashop&task=cart.removePromotion', false);
$removeUnavailable = Route::_('index.php?option=com_dharmashop&task=cart.removeUnavailable', false);
$hasErrors = (bool) array_filter($this->lines, static fn (object $line): bool => $line->error !== '');
$checkoutUrl = Route::_('index.php?option=com_dharmashop&view=checkout', false);
$discount = $this->discountTotals();
$qtyText = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
?>
<?php if (!$this->lines) : ?>
    <p class="text-body-secondary dharmaCartEmpty"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
<?php else : ?>
    <form action="<?php echo $this->escape($applyPromotion); ?>" method="post" id="dharmaPromotionForm"><?php echo HTMLHelper::_('form.token'); ?></form>
    <form action="<?php echo $this->escape($this->actionUrl); ?>" method="post" class="dharmaCartForm fs-6">
        <?php // Кнопка «Оформить заказ» с утилитами bg-primary/text-white: в шаблоне dharma_starter заливка .btn-primary не задана. Слева карточки позиций, справа панель итога (на узком экране — под списком). ?>
        <?php // Недоступные позиции: предупреждение и кнопки «Удалить недоступные» / «Уменьшить до наличия» (макет cart/unavailable, переопределяется). ?>
        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.unavailable', null, ['component' => 'none', 'client' => 0]))->render(['lines' => $this->lines, 'action' => $removeUnavailable, 'formId' => '', 'return' => 'cart']); ?>
        <div class="row g-4 align-items-start">
            <div class="col-lg-8 dharmaCartList">
                <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.lines', null, ['component' => 'none', 'client' => 0]))->render(['lines' => $this->lines, 'money' => $this->money, 'remove' => $remove]); ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php // Без JavaScript количество меняется этой кнопкой; с JavaScript пересчёт происходит сразу, и кнопки нет. ?>
                    <noscript><button type="submit" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_DHARMASHOP_CART_UPDATE'); ?></button></noscript>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?php echo $this->escape($clear); ?>" data-dharmashop-cartpage-clear><?php echo Text::_('COM_DHARMASHOP_CART_CLEAR'); ?></button>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="card rounded-3 shadow-sm sticky-lg-top dharmaCartSummary" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_TOTAL_SHORT')); ?>">
                    <div class="card-header bg-body-tertiary p-3">
                        <h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL_SHORT'); ?></h2>
                    </div>
                    <?php if ($discount['saved'] > 0 || $this->totals['adjustments']) : ?>
                    <div class="card-body p-3 small d-flex flex-column gap-2">
                        <?php if ($discount['saved'] > 0) : ?>
                            <div class="d-flex justify-content-between align-items-baseline gap-3"><span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CART_BEFORE_DISCOUNT'); ?></span><span class="fw-medium text-end text-nowrap"><?php echo $this->escape($this->money->format((string) $discount['before'])); ?></span></div>
                            <div class="d-flex justify-content-between align-items-baseline gap-3 text-success"><span><?php echo Text::_('COM_DHARMASHOP_CART_DISCOUNT'); ?></span><span class="fw-semibold text-end text-nowrap">−<?php echo $this->escape($this->money->format((string) $discount['saved'])); ?></span></div>
                        <?php endif; ?>
                        <?php foreach ($this->totals['adjustments'] as $adjustment) : ?>
                            <div class="d-flex justify-content-between align-items-baseline gap-3"><span class="text-body-secondary"><?php echo $this->escape($adjustment['title']); ?></span><span class="fw-medium text-end text-nowrap"><?php echo $this->escape($this->money->format((string) $adjustment['amount'])); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="card-footer bg-body p-3">
                        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.promotion-progress', null, ['component' => 'none', 'client' => 0]))->render(['progress' => $this->totals['promotion_progress'] ?? [], 'money' => $this->money]); ?>
                        <?php if (!empty($this->totals['promotion_input_available']) || !empty($this->totals['applied_code'])) : ?>
                        <div class="border-top pt-3 mb-3">
                            <label class="form-label small" for="dharma-cart-promotion-code"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_CODE'); ?></label>
                            <div class="input-group">
                                <input type="text" id="dharma-cart-promotion-code" class="form-control" name="promotion_code" form="dharmaPromotionForm" value="<?php echo $this->escape($this->totals['applied_code'] ?? ''); ?>" maxlength="64" autocomplete="off">
                                <button type="submit" class="btn btn-outline-primary" form="dharmaPromotionForm"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_APPLY'); ?></button>
                            </div>
                            <?php if (!empty($this->totals['promotion_error'])) : ?><div class="alert alert-warning small mt-2 mb-2" role="alert"><?php echo $this->escape(Text::_($this->totals['promotion_error'])); ?></div><?php endif; ?>
                            <?php if (!empty($this->totals['promotion_inactive']) && empty($this->totals['promotion_error'])) : ?><div class="form-text"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_INACTIVE_NOTE'); ?></div><?php endif; ?>
                            <?php if (!empty($this->totals['promotion_pending_identity'])) : ?><div class="form-text"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_IDENTITY_PENDING'); ?></div><?php endif; ?>
                            <?php if (!empty($this->totals['applied_description'])) : ?><div class="form-text"><?php echo $this->escape($this->totals['applied_description']); ?></div><?php endif; ?>
                            <?php if (!empty($this->totals['applied_code'])) : ?><button type="submit" class="btn btn-sm btn-link px-0" form="dharmaPromotionForm" formaction="<?php echo $this->escape($removePromotion); ?>"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_REMOVE'); ?></button><?php endif; ?>
                            <div class="form-text"><?php echo Text::_('COM_DHARMASHOP_PROMOTION_CART_HELP'); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
                            <span class="small fw-semibold"><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL'); ?></span>
                            <span class="fs-5 lh-sm fw-semibold text-end text-break"><?php echo $this->escape($this->totalText((float) $this->totals['total'])); ?></span>
                        </div>
                        <?php if ($hasErrors) : ?><div class="alert alert-warning small mb-3" role="alert"><?php echo Text::_('COM_DHARMASHOP_CART_HAS_ERRORS'); ?></div><?php endif; ?>
                        <a class="btn btn-primary bg-primary border-primary text-white w-100 py-2 fs-6 fw-semibold" href="<?php echo $this->escape($checkoutUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT'); ?></a>
                    </div>
                </aside>
            </div>
        </div>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>
