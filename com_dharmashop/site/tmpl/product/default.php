<?php

/** Страница товара: галерея, блок покупки и характеристики подключаются отдельными шаблонами/макетами. */

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;

// Будущий вид должен передать проверенный товар в item; без него событий нет.
if (!isset($this->item) || !is_object($this->item)) {
    return;
}
$page = ($this->seo ?? null) instanceof PageMetadata ? $this->seo : null;
$heading = $page?->h1 ?? (string) ($this->item->title ?? '');
?>
<div id="dharma_shop" class="dharmaProduct" data-product-id="<?php echo (int) $this->item->id; ?>" data-product-group="<?php echo (int) ($this->item->group_id ?? 0); ?>">
<section class="py-4">
    <?php echo $this->renderHook('beforeTitle'); ?>
    <?php if (($this->showHeading ?? true) && $heading !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($heading); ?></h1>
    <?php endif; ?>
    <?php echo $this->renderHook('afterTitle'); ?>
    <?php echo $this->renderHook('beforeContent'); ?>
    <div class="row g-4 align-items-start">
        <div class="col-12 col-md-6 col-xl-4 dharmaProductGallery">
            <?php echo $this->renderHook('beforeGallery'); ?>
            <?php // Галерея — отдельный файл рядом с default.php (default_gallery.php): правится независимо, виден в «Шаблоны → Переопределения». ?>
            <?php echo $this->loadTemplate('gallery'); ?>
            <?php echo $this->renderHook('afterGallery'); ?>
        </div>
        <div class="col-12 col-md-6 col-xl-5 dharmaProductInfo">
            <?php echo $this->renderHook('beforeIntro'); ?>
            <?php echo $this->loadTemplate('intro'); ?>
            <?php echo $this->renderHook('afterIntro'); ?>
        </div>
        <div class="col-12 col-xl-3 dharmaProductPurchase">
            <?php echo $this->renderHook('beforeBuy'); ?>
            <?php // Цена, наличие, артикул и кнопки — отдельный переопределяемый блок default_buy.php. ?>
            <?php echo $this->loadTemplate('buy'); ?>
            <?php echo $this->renderHook('afterBuy'); ?>
        </div>
    </div>
    <?php echo $this->renderHook('beforeTabs'); ?>
    <?php echo $this->loadTemplate('tabs'); ?>
    <?php echo $this->renderHook('afterTabs'); ?>
    <?php echo $this->renderHook('afterContent'); ?>
</section>
</div>
