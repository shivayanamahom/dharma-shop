<?php
/** Список брендов: SEO подготовлено видом, карточка подключается отдельным layout; макет можно править отдельно. */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;

$hooks = new LayoutHooks('category', 'brands', $this->parent, $this->params, $this->items, $this->seo);
?>
<div id="dharma_shop" class="dharmaBrands">
<section class="py-4">
    <?php echo $hooks->render('beforeTitle'); ?>
    <?php if ($this->showHeading && $this->seo->h1 !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($this->seo->h1); ?></h1>
    <?php endif; ?>
    <?php echo $hooks->render('afterTitle'); ?>
    <?php echo $hooks->render('beforeContent'); ?>
    <?php if ($this->image !== '') : ?>
        <img class="img-fluid rounded mb-4" src="<?php echo $this->escape($this->image); ?>" alt="<?php echo $this->escape((string) ($this->parent->params->get('image_alt') ?: $this->parent->title)); ?>" decoding="async">
    <?php endif; ?>
    <?php if (trim((string) $this->parent->params->get('introtext', '')) !== '') : ?>
        <div class="mb-4"><?php echo $this->text((string) $this->parent->params->get('introtext')); ?></div>
    <?php endif; ?>
    <?php if ($this->items) : ?>
        <div class="<?php echo $this->escape($this->gridClasses); ?>">
            <?php foreach ($this->items as $item) : ?>
                <div class="col"><?php echo $this->renderCard($item); ?></div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_NO_BRANDS'); ?></p>
    <?php endif; ?>
    <?php if (trim((string) $this->parent->description) !== '') : ?>
        <div class="mt-4"><?php echo $this->text((string) $this->parent->description); ?></div>
    <?php endif; ?>
    <?php echo $hooks->render('afterContent'); ?>
</section>
</div>
