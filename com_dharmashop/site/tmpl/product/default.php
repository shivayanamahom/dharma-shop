<?php

/** Заготовка макета страницы товара. Витринная разметка будет добавлена отдельно. */

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
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
<section class="py-4">
    <?php echo $hooks->render('beforeTitle'); ?>
    <?php if (($this->showHeading ?? true) && $heading !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($heading); ?></h1>
    <?php endif; ?>
    <?php echo $hooks->render('afterTitle'); ?>
    <?php echo $hooks->render('beforeContent'); ?>
    <?php // Здесь будет контент страницы товара. ?>
    <?php echo $hooks->render('afterContent'); ?>
</section>
