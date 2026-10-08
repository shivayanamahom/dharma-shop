<?php

/** Заготовка макета страницы товара. Витринная разметка будет добавлена отдельно. */

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;

// Будущий вид должен передать проверенный товар в item; без него событий нет.
if (!isset($this->item) || !is_object($this->item)) {
    return;
}
$params = ($this->params ?? null) instanceof Registry ? $this->params : new Registry();
$page = ($this->seo ?? null) instanceof PageMetadata ? $this->seo : null;
$hooks = new LayoutHooks('product', 'product', $this->item, $params, [], $page);
$heading = $page?->h1 ?? (string) ($this->item->title ?? '');
?>
<div id="dharma_shop" class="dharmaProduct">
<section class="py-4">
    <?php echo $hooks->render('beforeTitle'); ?>
    <?php if (($this->showHeading ?? true) && $heading !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($heading); ?></h1>
    <?php endif; ?>
    <?php echo $hooks->render('afterTitle'); ?>
    <?php echo $hooks->render('beforeContent'); ?>
    <div class="row g-4">
        <div class="col-lg-6 dharmaProductGallery">
            <?php // Галерея — отдельный макет (product-gallery): правится независимо от страницы. ?>
            <?php echo (new FileLayout('components.dharmashop.product-gallery.default', null, ['component' => 'none', 'client' => 0]))->render($this->gallery); ?>
        </div>
        <div class="col-lg-6 dharmaProductInfo">
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
    <?php // Остальные части карточки товара (параметры, корзина) подключаются отдельными макетами. ?>
    <?php echo (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $this->item->fields ?? [], 'context' => 'page']); ?>
        </div>
    </div>
    <?php echo $hooks->render('afterContent'); ?>
</section>
</div>
