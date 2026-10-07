<?php
/**
 * Карточка товара в сетке (Bootstrap card): область изображения с бейджами в левом верхнем углу, категория над названием,
 * футер с ценой слева (справа место под кнопку «В корзину»). Данные готовит вид, SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$image = MediaUrl::absolute((string) ($item->cover ?? ''));
$intro = trim(strip_tags((string) ($displayData['intro'] ?? $item->introtext ?? '')));
$price = (string) ($displayData['priceRange'] ?? '');
$oldPrice = (string) ($displayData['oldPrice'] ?? '');
$discount = (int) ($displayData['discountPercent'] ?? 0);
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
?>
<article class="card h-100 dharmaCard">
    <div class="position-relative">
        <div class="ratio ratio-1x1 overflow-hidden rounded-top bg-body-tertiary">
            <?php if ($image !== '') : ?>
                <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item->cover_alt ?: $item->title); ?>" loading="lazy" decoding="async">
            <?php endif; ?>
        </div>
        <?php if (!empty($displayData['badges'])) : ?>
            <div class="position-absolute top-0 start-0 d-flex flex-wrap gap-1 p-2 dharmaCardBadges">
                <?php foreach ($displayData['badges'] as $badge) : ?>
                    <a class="badge text-bg-<?php echo $escape($badge->color); ?> text-decoration-none" href="<?php echo $escape($badge->url ?? '#'); ?>"><?php echo $escape($badge->title); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($categoryTitle !== '') : ?>
            <div class="small text-body-secondary mb-1 dharmaCardCategory"><a class="link-secondary text-decoration-none" href="<?php echo $escape($displayData['categoryUrl'] ?? '#'); ?>"><?php echo $escape($categoryTitle); ?></a></div>
        <?php endif; ?>
        <h3 class="h5 card-title mb-0"><?php echo $escape($item->title); ?></h3>
        <?php if (!empty($displayData['showIntro']) && $intro !== '') : ?>
            <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-end gap-2 dharmaCardFooter">
        <div class="dharmaCardPrice">
            <?php if ($price !== '') : ?>
                <div class="fw-bold fs-5 lh-sm"><?php echo $escape($price); ?></div>
                <?php if ($oldPrice !== '') : ?>
                    <div class="small text-body-secondary">
                        <s><?php echo $escape($oldPrice); ?></s>
                        <?php if ($discount > 0) : ?><span class="badge text-bg-danger ms-1">−<?php echo $discount; ?>%</span><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="fw-semibold"><?php echo $escape(Text::_('COM_DHARMASHOP_PRODUCT_PRICE_REQUEST')); ?></div>
            <?php endif; ?>
            <?php if ((int) $item->in_stock === 0) : ?>
                <div class="small text-body-secondary"><?php echo $escape(Text::_('COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK')); ?></div>
            <?php endif; ?>
        </div>
        <?php // Справа: виджет количества (настройка «Выводить виджет количества в карточках») и кнопка «В корзину» (корзина — отдельный этап). ?>
        <?php if (!empty($displayData['showQty']) && (int) $item->in_stock !== 0 && $price !== '') : ?>
            <?php $step = (float) ($item->qty_step ?: 1); $min = (float) ($item->qty_min ?: $step); ?>
            <div class="input-group input-group-sm flex-nowrap w-auto dharmaCardQty">
                <input type="number" class="form-control" value="<?php echo $escape((string) $min); ?>" min="<?php echo $escape((string) $min); ?>" step="<?php echo $escape((string) $step); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                <?php if (!empty($item->unit)) : ?><span class="input-group-text"><?php echo $escape($item->unit); ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>
