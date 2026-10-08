<?php
/**
 * Галерея товара на странице товара: главный слайдер, полоса миниатюр и ссылки для лайтбокса. Отдельный файл — правится независимо
 * от страницы товара. Данные готовит вид, SQL здесь нет.
 *
 * displayData: images (объекты url, alt, title), type (swiper | bootstrap), thumbs (bottom | none), lightbox (bool),
 * loop (bool), id (int, id товара). Инициализацию Swiper и привязку лайтбокса делает media/com_dharmashop/js/product-gallery.js.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$images = array_values((array) ($displayData['images'] ?? []));

if (!$images) {
    return;
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = (int) ($displayData['id'] ?? 0);
$type = ($displayData['type'] ?? 'swiper') === 'bootstrap' ? 'bootstrap' : 'swiper';
$thumbs = ($displayData['thumbs'] ?? 'bottom') === 'none' ? 'none' : 'bottom';
$lightbox = !empty($displayData['lightbox']);
$group = 'dharmaGallery-' . $id;
$link = static function (object $image, string $content) use ($escape, $lightbox, $group): string {
    return $lightbox
        ? '<a class="d-block w-100 h-100" href="' . $escape($image->url) . '" data-fancybox="' . $escape($group) . '" data-caption="' . $escape($image->title ?? $image->alt) . '">' . $content . '</a>'
        : $content;
};
?>
<div class="dharmaGallery" id="<?php echo $escape($group); ?>" data-dharmashop-gallery data-type="<?php echo $type; ?>" data-thumbs="<?php echo $thumbs; ?>" data-loop="<?php echo !empty($displayData['loop']) ? '1' : '0'; ?>" data-lightbox="<?php echo $lightbox ? '1' : '0'; ?>">
    <?php if ($type === 'swiper' && count($images) > 0) : ?>
        <div class="swiper dharmaGalleryMain rounded overflow-hidden">
            <div class="swiper-wrapper">
                <?php foreach ($images as $n => $image) : ?>
                    <div class="swiper-slide">
                        <div class="ratio ratio-1x1 bg-body-tertiary">
                            <?php echo $link($image, '<img class="w-100 h-100 object-fit-contain" src="' . $escape($image->url) . '" alt="' . $escape($image->alt) . '"' . ($n === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($images) > 1) : ?>
                <?php // Стрелки и полоски — классы Bootstrap (carousel-*): вид не зависит от шаблона; до запуска слайдера скрыты (d-none). ?>
                <div class="carousel-indicators mx-0 d-none" data-dharmashop-slider-pagination></div>
                <button type="button" class="carousel-control-prev w-auto px-2 d-none" data-dharmashop-slider-prev aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                <button type="button" class="carousel-control-next w-auto px-2 d-none" data-dharmashop-slider-next aria-label="<?php echo $escape(Text::_('JNEXT')); ?>"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
            <?php endif; ?>
        </div>
        <?php if ($thumbs === 'bottom' && count($images) > 1) : ?>
            <div class="swiper dharmaGalleryThumbs mt-2" thumbsSlider="">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $image) : ?>
                        <div class="swiper-slide rounded overflow-hidden border">
                            <div class="ratio ratio-1x1 bg-body-tertiary"><img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($image->url); ?>" alt="" loading="lazy" decoding="async"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <?php $carousel = $group . '-carousel'; ?>
        <div id="<?php echo $escape($carousel); ?>" class="carousel slide rounded overflow-hidden" data-bs-ride="false" data-bs-touch="true">
            <?php if (count($images) > 1) : ?>
                <div class="carousel-indicators">
                    <?php foreach ($images as $n => $image) : ?>
                        <button type="button" data-bs-target="#<?php echo $escape($carousel); ?>" data-bs-slide-to="<?php echo (int) $n; ?>"<?php echo $n === 0 ? ' class="active" aria-current="true"' : ''; ?> aria-label="<?php echo (int) ($n + 1); ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="carousel-inner">
                <?php foreach ($images as $n => $image) : ?>
                    <div class="carousel-item<?php echo $n === 0 ? ' active' : ''; ?>">
                        <div class="ratio ratio-1x1 bg-body-tertiary">
                            <?php echo $link($image, '<img class="w-100 h-100 object-fit-contain" src="' . $escape($image->url) . '" alt="' . $escape($image->alt) . '"' . ($n === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($images) > 1) : ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $escape($carousel); ?>" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden"><?php echo $escape(Text::_('JPREVIOUS')); ?></span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $escape($carousel); ?>" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden"><?php echo $escape(Text::_('JNEXT')); ?></span></button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
