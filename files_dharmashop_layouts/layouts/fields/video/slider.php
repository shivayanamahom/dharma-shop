<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/video/slider.php */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$itemLayout = new FileLayout('components.dharmashop.fields.video.item', null, ['component'=>'none','client'=>0]);
?>
<div id="<?php echo $escape($gallery['id']); ?>" class="dharmaFieldVideo dharmaFieldVideo--slider" data-dharmashop-field-gallery data-layout="slider" data-columns="<?php echo $escape(json_encode($gallery['columns'], JSON_THROW_ON_ERROR)); ?>" data-lightbox="<?php echo $gallery['lightbox'] ? '1' : '0'; ?>">
    <div class="swiper" data-field-gallery-slider>
        <div class="swiper-wrapper align-items-stretch">
            <?php foreach ($field->values as $index=>$video) : ?>
                <div class="swiper-slide h-auto dharmaFieldVideo__item"><?php echo $itemLayout->render($displayData + ['video'=>$video, 'index'=>$index]); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3 d-none" data-field-gallery-controls>
        <button type="button" class="btn btn-outline-secondary" data-field-gallery-prev aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"><span aria-hidden="true">&larr;</span></button>
        <button type="button" class="btn btn-outline-secondary" data-field-gallery-next aria-label="<?php echo $escape(Text::_('JNEXT')); ?>"><span aria-hidden="true">&rarr;</span></button>
    </div>
</div>
