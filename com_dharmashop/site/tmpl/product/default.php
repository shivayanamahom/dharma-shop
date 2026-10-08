<?php

/** Страница товара: галерея, блок покупки и характеристики подключаются отдельными шаблонами/макетами. */

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
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
    <div class="row g-4 align-items-start">
        <div class="col-12 col-md-6 col-xl-4 dharmaProductGallery">
            <?php // Галерея — отдельный файл рядом с default.php (default_gallery.php): правится независимо, виден в «Шаблоны → Переопределения». ?>
            <?php echo $this->loadTemplate('gallery'); ?>
        </div>
        <div class="col-12 col-md-6 col-xl-5 dharmaProductInfo">
            <?php if (trim((string) ($this->item->introtext ?? '')) !== '') : ?>
                <?php // HTML краткого описания очищается фильтром формы товара при сохранении. ?>
                <div class="mb-4 dharmaIntro"><?php echo $this->text((string) $this->item->introtext); ?></div>
            <?php endif; ?>
            <?php // Характеристики подключаются отдельным макетом. ?>
            <?php echo (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $this->item->fields ?? [], 'context' => 'page']); ?>
        </div>
        <div class="col-12 col-xl-3 dharmaProductPurchase">
            <?php // Цена, наличие, артикул и кнопки — отдельный переопределяемый блок default_buy.php. ?>
            <?php echo $this->loadTemplate('buy'); ?>
        </div>
    </div>
    <?php echo $this->loadTemplate('tabs'); ?>
    <?php echo $hooks->render('afterContent'); ?>
</section>
</div>
