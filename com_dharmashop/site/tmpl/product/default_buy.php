<?php
/**
 * Блок покупки на странице товара (часть страницы, `loadTemplate('buy')`): цена, скидка, таймер акции, наличие, артикул и действия покупки — кнопка «В корзину»,
 * поле количества или виджет «убрать / число / +» (настройка «Режим кнопки на странице товара»). Лежит рядом с default.php, поэтому
 * виден в админке («Шаблоны → Переопределения») и правится независимо. Данные — цена/скидка и `$this->buy` (готовит вид); в режиме каталога
 * действия покупки скрыты, цена/наличие/артикул выводятся по настройкам.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$inStock = (int) $this->item->in_stock !== 0;
?>
<div class="mb-4 dharmaProductBuy">
    <?php if ($this->price !== '') : ?>
        <div class="mb-4">
            <div class="h2 fw-bold"><?php echo $this->escape($this->price); ?></div>
            <?php if (($this->showDiscount && $this->oldPrice !== '') || ($this->showSaleCountdown && (int) $this->item->sale_end_timestamp > time())) : ?>
                <?php if ($this->showSaleCountdown && (int) $this->item->sale_end_timestamp > time()) : ?>
                    <?php $saleEnd = (int) $this->item->sale_end_timestamp; $totalHours = (int) ceil(($saleEnd - time()) / 3600); $saleCountdownText = (int) floor($totalHours / 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_DAY_UNIT') . ' ' . ($totalHours % 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT'); $saleCountdownLabel = Text::_('COM_DHARMASHOP_SALE_TIME_LEFT'); ?>
                <?php endif; ?>
                <div class="d-flex flex-wrap align-items-center gap-2 text-body-secondary">
                    <?php if ($this->showDiscount && $this->oldPrice !== '') : ?>
                        <s><?php echo $this->escape($this->oldPrice); ?></s>
                        <span class="badge text-bg-danger"><?php echo $this->escape($this->discountLabel); ?></span>
                    <?php endif; ?>
                    <?php if ($this->showSaleCountdown && (int) $this->item->sale_end_timestamp > time()) : ?>
                        <span class="badge text-bg-warning" data-dharmashop-sale-countdown data-sale-end="<?php echo $saleEnd; ?>" data-sale-prefix="<?php echo $this->escape(Text::_('COM_DHARMASHOP_SALE_TIME_FALLBACK')); ?>" data-sale-label="<?php echo $this->escape($saleCountdownLabel); ?>" data-sale-days="<?php echo $this->escape(Text::_('COM_DHARMASHOP_SALE_DAY_UNIT')); ?>" data-sale-hours="<?php echo $this->escape(Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT')); ?>" aria-label="<?php echo $this->escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>" title="<?php echo $this->escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>"><?php echo $this->escape(Text::_('COM_DHARMASHOP_SALE_TIME_FALLBACK') . ' ' . $saleCountdownText); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-body-secondary">
        <span class="<?php echo $inStock ? 'text-success' : ''; ?>"><?php echo Text::_($inStock ? 'COM_DHARMASHOP_PRODUCT_IN_STOCK' : 'COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK'); ?></span>
        <?php if ($this->item->sku !== '') : ?><span><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $this->escape($this->item->sku); ?></span><?php endif; ?>
    </div>
    <div class="dharmaProductBuyActions">
        <?php echo (new FileLayout('components.dharmashop.product-card-parts.actions', null, ['component' => 'none', 'client' => 0]))->render($this->buy); ?>
    </div>
    <?php if ($this->stepperTemplate !== '') : ?><template data-dharmashop-stepper-template><?php echo $this->stepperTemplate; ?></template><?php endif; ?>
</div>
