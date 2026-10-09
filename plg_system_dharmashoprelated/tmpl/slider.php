<?php
/** Override: html/plg_system_dharmashoprelated/slider.php. Cards use the selected component/plugin grid layout. */
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->getRegistry()->addRegistryFile('media/plg_system_dharmashoprelated/joomla.asset.json');
$wa->usePreset('dharmashop.swiper')->useScript('plg_system_dharmashoprelated.slider');
$options = ['gap'=>$block['slider_gap'], 'speed'=>$block['slider_speed'], 'loop'=>(bool) $block['slider_loop'],
    'autoplay'=>(bool) $block['slider_autoplay'], 'delay'=>$block['slider_delay'], 'columns'=>[]];
foreach (['xs','sm','md','lg','xl','xxl'] as $breakpoint) { $options['columns'][$breakpoint] = $block['slider_columns_' . $breakpoint]; }
?>
<div class="dharmaRelatedSlider" data-dharmashop-related-slider data-slider-options="<?php echo $escape(json_encode($options, JSON_THROW_ON_ERROR)); ?>">
    <div class="swiper" data-related-track>
        <div class="swiper-wrapper align-items-stretch">
            <?php foreach ($items as $item) : ?><div class="swiper-slide h-auto"><?php echo $renderCard($item); ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php if ($block['slider_arrows'] || $block['slider_pagination']) : ?>
    <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 mt-3 d-none" data-related-controls>
        <?php if ($block['slider_arrows']) : ?>
        <button type="button" class="btn btn-outline-secondary" data-related-prev aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"><span aria-hidden="true">&larr;</span></button>
        <?php endif; ?>
        <?php if ($block['slider_pagination']) : ?><div class="carousel-indicators position-static flex-wrap m-0" data-related-pagination></div><?php endif; ?>
        <?php if ($block['slider_arrows']) : ?>
        <button type="button" class="btn btn-outline-secondary" data-related-next aria-label="<?php echo $escape(Text::_('JNEXT')); ?>"><span aria-hidden="true">&rarr;</span></button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
