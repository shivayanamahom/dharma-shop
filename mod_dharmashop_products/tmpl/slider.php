<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<div data-dharmashop-products-slider="<?php echo (int) $moduleId; ?>" data-slider-options="<?php echo $escape(json_encode($sliderOptions, JSON_THROW_ON_ERROR)); ?>">
    <div class="swiper" data-products-track>
        <div class="swiper-wrapper align-items-stretch">
            <?php foreach ($items as $item) : ?><div class="swiper-slide h-auto"><?php echo $renderCard($item); ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php if ($sliderOptions['arrows'] || $sliderOptions['pagination']) : ?>
        <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 mt-3 d-none" data-products-controls>
            <?php if ($sliderOptions['arrows']) : ?><button type="button" class="btn btn-outline-secondary" data-products-prev aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"><span aria-hidden="true">&larr;</span></button><?php endif; ?>
            <?php if ($sliderOptions['pagination']) : ?><div class="carousel-indicators position-static flex-wrap m-0" data-products-pagination></div><?php endif; ?>
            <?php if ($sliderOptions['arrows']) : ?><button type="button" class="btn btn-outline-secondary" data-products-next aria-label="<?php echo $escape(Text::_('JNEXT')); ?>"><span aria-hidden="true">&rarr;</span></button><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
