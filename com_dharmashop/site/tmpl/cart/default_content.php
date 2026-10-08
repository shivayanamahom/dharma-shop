<?php
/**
 * Содержимое страницы корзины: пустая корзина или таблица позиций с итогами. Подгружается и целиком заменяется скриптом cart-page.js
 * после каждого действия (фрагмент `layout=page&format=raw`). Только классы Bootstrap; data-атрибуты — для скрипта; без JavaScript
 * работают формы (удаление, очистка и скрытая в браузере с JavaScript кнопка «Пересчитать»).
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$remove = Route::_('index.php?option=com_dharmashop&task=cart.remove', false);
$clear = Route::_('index.php?option=com_dharmashop&task=cart.clear', false);
$hasErrors = (bool) array_filter($this->lines, static fn (object $line): bool => $line->error !== '');
$checkoutUrl = Route::_('index.php?option=com_dharmashop&view=checkout', false);
$discount = $this->discountTotals();
$qtyText = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
?>
<?php if (!$this->lines) : ?>
    <p class="text-body-secondary dharmaCartEmpty"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
    <a class="btn btn-sm btn-outline-primary" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
<?php else : ?>
    <form action="<?php echo $this->escape($this->actionUrl); ?>" method="post" class="dharmaCartForm fs-6">
        <?php // Кнопка «Оформить заказ» с утилитами bg-primary/text-white: в шаблоне dharma_starter заливка .btn-primary не задана. Слева карточки позиций, справа панель итога (на узком экране — под списком). ?>
        <div class="row g-4 align-items-start">
            <div class="col-lg-8 dharmaCartList">
                <?php foreach ($this->lines as $line) : ?>
                    <?php
                    $step = (float) ($line->qty_step ?: 1);
                    $min = (float) ($line->qty_min ?: $step);
                    $onSale = $line->error === '' && $line->base_price !== null && $line->unit_price !== null && $line->unit_price > 0 && $line->base_price > $line->unit_price;
                    $showUnit = $line->unit_price !== null && !($this->money->replacesZero() && $line->unit_price === 0.0);
                    ?>
                    <div class="card shadow-sm mb-3 position-relative dharmaCartLine" data-line="<?php echo (int) $line->id; ?>">
                        <div class="card-body d-flex gap-3">
                            <?php if ($line->image !== '') : ?>
                                <a class="flex-shrink-0 align-self-start" href="<?php echo $this->escape($line->url); ?>" tabindex="-1" aria-hidden="true"><img class="rounded border object-fit-contain bg-white" src="<?php echo $this->escape($line->image); ?>" width="96" height="96" alt="" loading="lazy" decoding="async"></a>
                            <?php endif; ?>
                            <div class="flex-grow-1 min-w-0 pe-5">
                                <?php if ((string) $line->category_title !== '') : ?><div class="small text-body-secondary"><?php echo $this->escape($line->category_title); ?></div><?php endif; ?>
                                <a class="fs-6 fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $this->escape($line->url); ?>"><?php echo $this->escape($line->title); ?></a>
                                <?php if ($line->variant_title) : ?><div class="small text-body-secondary"><?php echo $this->escape($line->variant_title); ?></div><?php endif; ?>
                                <?php if ($line->sku) : ?><div class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $this->escape($line->sku); ?></div><?php endif; ?>
                                <?php if ($line->error !== '') : ?><div class="small text-danger"><?php echo $this->escape(Text::_($line->error)); ?></div><?php endif; ?>
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
                                    <div class="input-group input-group-sm flex-nowrap w-auto dharmaCartQty" data-dharmashop-cartpage-stepper>
                                        <button type="button" class="btn btn-outline-secondary px-3" data-dharmashop-cartpage-step="down" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>">−</button>
                                        <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" name="qty[<?php echo (int) $line->id; ?>]" value="<?php echo $this->escape($qtyText($line->qty)); ?>" data-dharmashop-cartpage-qty="<?php echo (int) $line->id; ?>" data-min="<?php echo $this->escape((string) $min); ?>" data-step="<?php echo $this->escape((string) $step); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                                        <button type="button" class="btn btn-outline-secondary px-3" data-dharmashop-cartpage-step="up" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>">+</button>
                                    </div>
                                    <?php if ($line->error === '') : ?>
                                        <div class="text-end lh-sm">
                                            <?php if ($onSale) : ?><div class="small text-body-secondary"><s><?php echo $this->escape($this->money->format((string) $line->base_price)); ?></s></div><?php endif; ?>
                                            <?php if ($showUnit) : ?><div class="small text-body-secondary"><?php echo $this->escape($this->money->price((string) $line->unit_price)); ?><?php echo (string) $line->unit !== '' ? ' / ' . $this->escape($line->unit) : ''; ?></div><?php endif; ?>
                                            <div class="fw-semibold fs-6"><?php echo $this->escape($this->money->price((string) $line->total)); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-light rounded-circle p-2 d-inline-flex align-items-center justify-content-center position-absolute top-0 end-0 m-3" name="item_id" value="<?php echo (int) $line->id; ?>" formaction="<?php echo $this->escape($remove); ?>" data-dharmashop-cartpage-remove="<?php echo (int) $line->id; ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m4 4 8 8M12 4l-8 8" /></svg></button>
                    </div>
                <?php endforeach; ?>
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
