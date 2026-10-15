<?php

/** Краткое описание товара и только специально включённые для интро характеристики. */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

$fields = array_filter((array) ($this->item->fields ?? []), static fn (object $field): bool => !empty($field->show_intro) && !empty($field->values));
$brandTitle = trim((string) ($this->item->brand_title ?? ''));
$brandImage = $this->params->get('product_page_brand_display', 'text') === 'image'
    ? ImageUrl::resolve((string) ($this->item->brand_image ?? ''), 'product.brand')
    : '';
?>
<?php if ($brandTitle !== '' && $this->brandUrl !== '') : ?>
    <div class="mb-3 dharmaProductBrand">
        <a href="<?php echo $this->escape($this->brandUrl); ?>" aria-label="<?php echo $this->escape($brandTitle); ?>">
            <?php if ($brandImage !== '') : ?>
                <img class="d-block mw-100 dharmaProductBrandImage" src="<?php echo $this->escape($brandImage); ?>" height="60" alt="<?php echo $this->escape((string) ($this->item->brand_image_alt ?? $brandTitle)); ?>" decoding="async">
            <?php else : ?>
                <?php echo $this->escape($brandTitle); ?>
            <?php endif; ?>
        </a>
    </div>
<?php endif; ?>
<?php if (trim((string) ($this->item->introtext ?? '')) !== '') : ?>
    <div class="mb-4 dharmaIntro"><?php echo $this->text((string) $this->item->introtext); ?></div>
<?php endif; ?>
<?php echo $this->renderHook('beforeIntroFields'); ?>
<?php if ($fields) : ?>
    <div class="dharmaProductIntroFields">
        <?php echo (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $fields, 'context' => 'intro']); ?>
    </div>
<?php endif; ?>
<?php echo $this->renderHook('afterIntroFields'); ?>
