<?php
/** Общая часть карточек товара: область изображения (фото или карусель Bootstrap) с бейджами и обратным отсчётом акции. Параметры displayData: mediaRounded = top|start, mediaRatio (1x1, 4x3…). */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$imageContext = (string) ($displayData['imageContext'] ?? 'product.category.grid');
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$discount = (float) ($displayData['discountPercent'] ?? 0);
$percentText = $discount > 0 && round($discount, 2) === 0.0
    ? '<0,01'
    : rtrim(rtrim(number_format($discount, 2, ',', "\u{00A0}"), '0'), ',');
$saleEnd = (int) ($displayData['saleEndTimestamp'] ?? 0);
$showSaleCountdown = !empty($displayData['showSaleCountdown']) && $saleEnd > time();
$favoritesEnabled = !empty($displayData['favoritesEnabled']);
$saleCountdownLabel = Text::_('COM_DHARMASHOP_SALE_TIME_LEFT');
$saleCountdownTotalHours = (int) ceil(($saleEnd - time()) / 3600);
$saleCountdownText = (int) floor($saleCountdownTotalHours / 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_DAY_UNIT') . ' ' . ($saleCountdownTotalHours % 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT');
$saleBadgeId = (int) ($displayData['saleBadgeId'] ?? 0);
$saleBadgeHasCountdown = false;
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$rounded = ($displayData['mediaRounded'] ?? 'top') === 'start' ? 'rounded-start' : 'rounded-top';
$ratio = preg_replace('/[^0-9x]/', '', (string) ($displayData['mediaRatio'] ?? '1x1')) ?: '1x1';

?>
    <div class="position-relative">
        <?php $images = (array) ($displayData['images'] ?? []); ?>
        <?php if (count($images) > 1 && ($displayData['sliderType'] ?? 'bootstrap') === 'swiper') : ?>
            <?php // Swiper (плагин «Dharma Shop Swiper»): создаётся лениво скриптом card-slider.js, до этого видна первая картинка. ?>
            <div class="swiper <?php echo $rounded; ?> overflow-hidden dharmaCardSlider" data-dharmashop-card-slider>
                <div class="swiper-wrapper">
                    <?php foreach ($images as $n => $image) : ?>
                        <div class="swiper-slide">
                            <a class="d-block text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                                <div class="ratio ratio-<?php echo $ratio; ?> bg-body-tertiary">
                                    <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($image->url, $imageContext)); ?>"<?php echo ImageUrl::attrs($image->url, $imageContext); ?> alt="<?php echo $escape($image->alt); ?>"<?php echo $n === 0 ? '' : ' loading="lazy"'; ?> decoding="async">
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php // Стрелки и полоски — классы Bootstrap (carousel-*): вид не зависит от шаблона; до запуска слайдера скрыты (d-none). ?>
                <div class="carousel-indicators mx-0 d-none" data-dharmashop-slider-pagination></div>
                <button type="button" class="carousel-control-prev w-auto px-2 d-none" data-dharmashop-slider-prev aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                <button type="button" class="carousel-control-next w-auto px-2 d-none" data-dharmashop-slider-next aria-label="<?php echo $escape(Text::_('JNEXT')); ?>"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
            </div>
        <?php elseif (count($images) > 1) : ?>
            <?php $carousel = 'dharmaCarousel-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($displayData['instanceId'] ?? (int) $item->id)); ?>
            <div id="<?php echo $carousel; ?>" class="carousel slide <?php echo $rounded; ?> overflow-hidden dharmaCardCarousel" data-bs-ride="false" data-bs-touch="true">
                <div class="carousel-indicators">
                    <?php foreach ($images as $n => $image) : ?>
                        <button type="button" data-bs-target="#<?php echo $carousel; ?>" data-bs-slide-to="<?php echo (int) $n; ?>"<?php echo $n === 0 ? ' class="active" aria-current="true"' : ''; ?> aria-label="<?php echo (int) ($n + 1); ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($images as $n => $image) : ?>
                        <div class="carousel-item<?php echo $n === 0 ? ' active' : ''; ?>">
                            <a class="d-block text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                                <div class="ratio ratio-<?php echo $ratio; ?> bg-body-tertiary">
                                    <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($image->url, $imageContext)); ?>"<?php echo ImageUrl::attrs($image->url, $imageContext); ?> alt="<?php echo $escape($image->alt); ?>"<?php echo $n === 0 ? '' : ' loading="lazy"'; ?> decoding="async">
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $carousel; ?>" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden"><?php echo $escape(Text::_('JPREVIOUS')); ?></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $carousel; ?>" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden"><?php echo $escape(Text::_('JNEXT')); ?></span>
                </button>
            </div>
        <?php else : ?>
            <div class="ratio ratio-<?php echo $ratio; ?> overflow-hidden <?php echo $rounded; ?> bg-body-tertiary">
                <?php if ($images) : ?>
                    <a class="d-block w-100 h-100 text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                        <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($images[0]->url, $imageContext)); ?>"<?php echo ImageUrl::attrs($images[0]->url, $imageContext); ?> alt="<?php echo $escape($images[0]->alt); ?>" loading="lazy" decoding="async">
                    </a>
                <?php else : ?>
                    <a class="d-block w-100 h-100 text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                        <?php echo (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render([]); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($displayData['badges']) || $showSaleCountdown) : ?>
            <div class="position-absolute top-0 start-0 d-flex flex-wrap align-items-center gap-2 p-2 z-1 dharmaCardBadges">
                <?php foreach ((array) ($displayData['badges'] ?? []) as $badge) : ?>
                    <a class="badge text-bg-<?php echo $escape($badge->color); ?> text-decoration-none" href="<?php echo $escape($badge->url ?? '#'); ?>"><?php echo $escape($badge->title); ?><?php if ($showSaleCountdown && $saleBadgeId > 0 && (int) $badge->id === $saleBadgeId) : ?><?php $saleBadgeHasCountdown = true; ?><span class="ms-1" data-dharmashop-sale-countdown data-sale-end="<?php echo $saleEnd; ?>" data-sale-prefix="<?php echo $escape('· ' . Text::_('COM_DHARMASHOP_SALE_TIME_SHORT')); ?>" data-sale-label="<?php echo $escape($saleCountdownLabel); ?>" data-sale-days="<?php echo $escape(Text::_('COM_DHARMASHOP_SALE_DAY_UNIT')); ?>" data-sale-hours="<?php echo $escape(Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT')); ?>" aria-label="<?php echo $escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>" title="<?php echo $escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>"><?php echo $escape('· ' . Text::_('COM_DHARMASHOP_SALE_TIME_SHORT') . ' ' . $saleCountdownText); ?></span><?php endif; ?></a>
                <?php endforeach; ?>
                <?php if ($showSaleCountdown && !$saleBadgeHasCountdown) : ?>
                    <span class="badge text-bg-warning" data-dharmashop-sale-countdown data-sale-end="<?php echo $saleEnd; ?>" data-sale-prefix="<?php echo $escape(Text::_('COM_DHARMASHOP_SALE_TIME_FALLBACK')); ?>" data-sale-label="<?php echo $escape($saleCountdownLabel); ?>" data-sale-days="<?php echo $escape(Text::_('COM_DHARMASHOP_SALE_DAY_UNIT')); ?>" data-sale-hours="<?php echo $escape(Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT')); ?>" aria-label="<?php echo $escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>" title="<?php echo $escape($saleCountdownLabel . ' ' . $saleCountdownText); ?>"><?php echo $escape(Text::_('COM_DHARMASHOP_SALE_TIME_FALLBACK') . ' ' . $saleCountdownText); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php echo (string) ($displayData['favoriteMediaActions'] ?? ''); ?>
        <?php if ($favoritesEnabled) : ?>
            <form class="position-absolute top-0 end-0 m-2 z-2" method="post" action="<?php echo $escape(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&task=favorites.toggle', false)); ?>">
                <input type="hidden" name="product_id" value="<?php echo (int) $item->id; ?>"><input type="hidden" name="<?php echo $escape($displayData['favoritesTokenName'] ?? ''); ?>" value="1">
                <?php $activeClass = preg_replace('/[^a-z0-9_-]/i', '', (string) ($displayData['favoriteActiveClass'] ?? 'text-danger')) ?: 'text-danger'; ?>
                <button type="submit" class="btn btn-light rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center p-2 lh-1<?php echo !empty($displayData['favorite']) ? ' ' . $activeClass : ''; ?>" data-favorites-toggle data-product-id="<?php echo (int) $item->id; ?>" data-token-name="<?php echo $escape($displayData['favoritesTokenName'] ?? ''); ?>" data-favorite-active-class="<?php echo $escape($activeClass); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_FAVORITES_TOGGLE')); ?>" aria-pressed="<?php echo !empty($displayData['favorite']) ? 'true' : 'false'; ?>">
                    <?php echo (string) ($displayData['favoriteIcon'] ?? ''); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
