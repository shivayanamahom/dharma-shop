<?php
/**
 * Карточки позиций корзины: фото, название, количество («−» / поле / «+»), цена и удаление. Общий макет страницы корзины и
 * оформления «в один шаг» (C100); кнопки и поля размечены атрибутами `data-dharmashop-cartpage-*` для cart-page.js, а без
 * JavaScript работают обычными кнопками формы. Переопределяется в шаблоне сайта: html/layouts/components/dharmashop/cart/lines.php.
 *
 * Данные: `lines` (позиции корзины), `money` (MoneyFormatter), `remove` (адрес действия удаления).
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Language\Text;

$lines = (array) $displayData['lines'];
$money = $displayData['money'];
$remove = (string) $displayData['remove'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$qtyText = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
?>
<?php foreach ($lines as $line) : ?>
    <?php
    $step = (float) ($line->qty_step ?: 1);
    $min = (float) ($line->qty_min ?: $step);
    $onSale = $line->error === '' && $line->base_price !== null && $line->unit_price !== null && $line->unit_price > 0 && $line->base_price > $line->unit_price;
    $showUnit = $line->unit_price !== null && !(($money->replacesZero() || !empty($line->zero_free)) && $line->unit_price === 0.0);
    ?>
    <div class="card shadow-sm mb-3 position-relative dharmaCartLine" data-line="<?php echo (int) $line->id; ?>">
        <div class="card-body d-flex gap-3">
            <?php if ($line->image !== '') : ?>
                <a class="flex-shrink-0 align-self-start" href="<?php echo $escape($line->url); ?>" tabindex="-1" aria-hidden="true"><img class="rounded border object-fit-contain bg-white" src="<?php echo $escape(ImageUrl::resolve($line->image, 'cart.page')); ?>" width="96" height="96" alt="" loading="lazy" decoding="async"></a>
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0 pe-5">
                <?php if ((string) $line->category_title !== '') : ?><div class="small text-body-secondary"><?php echo $escape($line->category_title); ?></div><?php endif; ?>
                <a class="fs-6 fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $escape($line->url); ?>"><?php echo $escape($line->title); ?></a>
                <?php if ($line->variant_title) : ?><div class="small text-body-secondary"><?php echo $escape($line->variant_title); ?></div><?php endif; ?>
                <?php if ($line->sku) : ?><div class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $escape($line->sku); ?></div><?php endif; ?>
                <?php if ($line->error !== '') : ?><div class="small text-danger"><?php echo $escape(Text::_($line->error)); ?></div><?php endif; ?>
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
                    <div class="input-group input-group-sm flex-nowrap w-auto dharmaCartQty" data-dharmashop-cartpage-stepper>
                        <button type="button" class="btn btn-outline-secondary px-3" data-dharmashop-cartpage-step="down" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>" title="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>">−</button>
                        <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" name="qty[<?php echo (int) $line->id; ?>]" value="<?php echo $escape($qtyText($line->qty)); ?>" data-dharmashop-cartpage-qty="<?php echo (int) $line->id; ?>" data-min="<?php echo $escape((string) $min); ?>" data-step="<?php echo $escape((string) $step); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                        <button type="button" class="btn btn-outline-secondary px-3" data-dharmashop-cartpage-step="up" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>" title="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>">+</button>
                    </div>
                    <?php if ($line->error === '') : ?>
                        <div class="text-end lh-sm">
                            <?php if ($onSale) : ?><div class="small text-body-secondary"><s><?php echo $escape($money->format((string) $line->base_price)); ?></s></div><?php endif; ?>
                            <?php if ($showUnit) : ?><div class="small text-body-secondary"><?php echo $escape($money->price((string) $line->unit_price)); ?><?php echo (string) $line->unit !== '' ? ' / ' . $escape($line->unit) : ''; ?></div><?php endif; ?>
                            <div class="fw-semibold fs-6"><?php echo $escape($money->price((string) $line->total, !empty($line->zero_free))); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-light rounded-circle p-2 d-inline-flex align-items-center justify-content-center position-absolute top-0 end-0 m-3" name="item_id" value="<?php echo (int) $line->id; ?>" formaction="<?php echo $escape($remove); ?>" data-dharmashop-cartpage-remove="<?php echo (int) $line->id; ?>" title="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m4 4 8 8M12 4l-8 8" /></svg></button>
    </div>
<?php endforeach; ?>
