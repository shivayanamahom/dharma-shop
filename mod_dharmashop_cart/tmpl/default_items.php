<?php
/**
 * Содержимое боковой панели мини-корзины (шаблон модуля `mod_dharmashop_cart`, переопределяется в
 * `templates/<шаблон>/html/mod_dharmashop_cart/default_items.php`): карточки позиций (категория, название, артикул, количество «−»/«+»,
 * цена за единицу, скидка и сумма, удаление), закреплённый внизу блок итога и кнопки «Корзина» и «Оформить заказ» (страницы cart и
 * checkout — разные). Подгружается скриптом mini-cart.js фрагментом компонента `view=cart&layout=mini&format=raw&mid=<модуль>`:
 * компонент готовит данные корзины посетителя, а разметка берётся отсюда. Шаблон подключается внутри метода вида корзины, поэтому
 * `$this` — вид (`$this->lines`, `$this->totals`, `$this->money`, `$this->mini`, `$this->escape()`, `$this->totalText()`); SQL здесь нет.
 * Стили — media/mod_dharmashop_cart/css/minicart.css. Действия (количество, удаление) выполняет mini-cart.js по data-атрибутам.
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$cartUrl = Route::_('index.php?option=com_dharmashop&view=cart', false);
$checkoutUrl = Route::_('index.php?option=com_dharmashop&view=checkout', false);
$qtyText = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
?>
<div class="dharmaMiniCart" data-dharmashop-minicart-content>
<?php if (!$this->lines) : ?>
    <div class="dharmaMiniCartEmpty">
        <p class="text-body-secondary mb-3"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="offcanvas"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></button>
    </div>
<?php else : ?>
    <ul class="dharmaMiniCartList">
        <?php foreach ($this->lines as $line) : ?>
            <?php
            $step = (float) ($line->qty_step ?: 1);
            $min = (float) ($line->qty_min ?: $step);
            $showImage = $this->mini['image'] && $line->image !== '';
            $unit = (string) $line->unit;
            // Скидка: обычная цена единицы выше итоговой (скидку и её срок считает магазин при сохранении цен; закончилась — цены равны).
            $discount = $this->mini['discount'] && $line->error === '' && $line->base_price !== null && $line->unit_price !== null
                && $line->unit_price > 0 && $line->base_price > $line->unit_price;
            ?>
            <li class="dharmaMiniCartLine<?php echo $line->error !== '' ? ' dharmaMiniCartLineError' : ''; ?>" data-line="<?php echo (int) $line->id; ?>">
                <?php if ($showImage) : ?>
                    <a class="dharmaMiniCartImage" href="<?php echo $this->escape($line->url); ?>" tabindex="-1" aria-hidden="true"><img src="<?php echo $this->escape(ImageUrl::resolve($line->image, 'cart.module')); ?>" width="72" height="72" alt="" loading="lazy" decoding="async"></a>
                <?php endif; ?>
                <div class="dharmaMiniCartInfo">
                    <?php if ($this->mini['category'] && (string) $line->category_title !== '') : ?>
                        <div class="dharmaMiniCartCategory"><?php echo $this->escape($line->category_title); ?></div>
                    <?php endif; ?>
                    <a class="dharmaMiniCartTitle" href="<?php echo $this->escape($line->url); ?>"><?php echo $this->escape($line->title); ?></a>
                    <?php if ($line->variant_title) : ?><div class="dharmaMiniCartMeta"><?php echo $this->escape($line->variant_title); ?></div><?php endif; ?>
                    <?php if ($this->mini['sku'] && (string) $line->sku !== '') : ?>
                        <div class="dharmaMiniCartMeta"><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $this->escape($line->sku); ?></div>
                    <?php endif; ?>
                    <?php if ($line->error !== '') : ?><div class="dharmaMiniCartMessage"><?php echo $this->escape(Text::_($line->error)); ?></div><?php endif; ?>
                    <div class="dharmaMiniCartBottom">
                        <div class="dharmaMiniCartQty" data-dharmashop-minicart-stepper>
                            <button type="button" data-dharmashop-minicart-step="down" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_MINUS')); ?>">−</button>
                            <input type="text" inputmode="decimal" size="3" value="<?php echo $this->escape($qtyText($line->qty)); ?>" data-dharmashop-minicart-qty="<?php echo (int) $line->id; ?>" data-min="<?php echo $this->escape((string) $min); ?>" data-step="<?php echo $this->escape((string) $step); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                            <button type="button" data-dharmashop-minicart-step="up" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>">+</button>
                        </div>
                        <?php if ($line->error === '') : ?>
                            <div class="dharmaMiniCartPrices">
                                <?php if ($discount) : ?>
                                    <span class="dharmaMiniCartOldPrice"><s><?php echo $this->escape($this->money->format((string) $line->base_price)); ?></s></span>
                                <?php endif; ?>
                                <?php if ($this->mini['unit'] && $line->unit_price !== null && !(($this->money->replacesZero() || !empty($line->zero_free)) && $line->unit_price === 0.0)) : ?>
                                    <span class="dharmaMiniCartUnitPrice"><?php echo $this->escape($this->money->price((string) $line->unit_price)); ?><?php echo $unit !== '' ? ' / ' . $this->escape($unit) : ''; ?></span>
                                <?php endif; ?>
                                <span class="dharmaMiniCartTotal"><?php echo $this->escape($this->money->price((string) $line->total, !empty($line->zero_free))); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="dharmaMiniCartRemove" data-dharmashop-minicart-remove="<?php echo (int) $line->id; ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="dharmaMiniCartFooter">
        <?php foreach ($this->totals['adjustments'] as $adjustment) : ?>
            <div class="dharmaMiniCartSum dharmaMiniCartAdjustment"><span><?php echo $this->escape((string) $adjustment['title']); ?></span><span><?php echo $this->escape($this->money->format((string) $adjustment['amount'])); ?></span></div>
        <?php endforeach; ?>
        <div class="dharmaMiniCartSum dharmaMiniCartSumTotal"><span><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL_SHORT'); ?></span><span><?php echo $this->escape($this->totalText((float) $this->totals['total'])); ?></span></div>
        <div class="dharmaMiniCartActions">
            <a class="<?php echo $this->escape($this->mini['cart_class']); ?> dharmaMiniCartGo" href="<?php echo $this->escape($cartUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_TITLE'); ?></a>
            <?php if ($this->mini['checkout']) : ?>
                <a class="<?php echo $this->escape($this->mini['checkout_class']); ?> dharmaMiniCartCheckout" href="<?php echo $this->escape($checkoutUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT'); ?></a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>
