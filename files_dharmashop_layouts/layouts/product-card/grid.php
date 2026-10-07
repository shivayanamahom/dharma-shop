<?php
/**
 * Карточка товара в сетке (Bootstrap card): область изображения с бейджами в левом верхнем углу, категория над названием,
 * футер с ценой слева и количеством с кнопкой «В корзину» справа. Данные готовит вид, SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$intro = trim(strip_tags((string) ($displayData['intro'] ?? $item->introtext ?? '')));
$price = (string) ($displayData['priceRange'] ?? '');
$oldPrice = (string) ($displayData['oldPrice'] ?? '');
$discount = (float) ($displayData['discountPercent'] ?? 0);
$discountAmount = (string) ($displayData['discountAmount'] ?? '');
$showDiscount = !empty($displayData['showDiscount']);
$discountDisplay = ($displayData['discountDisplay'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
$percentText = $discount > 0 && round($discount, 2) === 0.0
    ? '<0,01'
    : rtrim(rtrim(number_format($discount, 2, ',', "\u{00A0}"), '0'), ',');
$discountText = $discountDisplay === 'amount' ? '−' . $discountAmount : '−' . $percentText . '%';
$saleEnd = (int) ($displayData['saleEndTimestamp'] ?? 0);
$showSaleCountdown = !empty($displayData['showSaleCountdown']) && $saleEnd > time();
$saleCountdownLabel = Text::_('COM_DHARMASHOP_SALE_TIME_LEFT');
$saleCountdownTotalHours = (int) ceil(($saleEnd - time()) / 3600);
$saleCountdownText = (int) floor($saleCountdownTotalHours / 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_DAY_UNIT') . ' ' . ($saleCountdownTotalHours % 24) . ' ' . Text::_('COM_DHARMASHOP_SALE_HOUR_UNIT');
$saleBadgeId = (int) ($displayData['saleBadgeId'] ?? 0);
$saleBadgeHasCountdown = false;
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
?>
<article class="card h-100 dharmaCard">
    <div class="position-relative">
        <?php $images = (array) ($displayData['images'] ?? []); ?>
        <?php if (count($images) > 1) : ?>
            <?php $carousel = 'dharmaCarousel-' . (int) $item->id; ?>
            <div id="<?php echo $carousel; ?>" class="carousel slide rounded-top overflow-hidden dharmaCardCarousel" data-bs-ride="false" data-bs-touch="true">
                <div class="carousel-indicators">
                    <?php foreach ($images as $n => $image) : ?>
                        <button type="button" data-bs-target="#<?php echo $carousel; ?>" data-bs-slide-to="<?php echo (int) $n; ?>"<?php echo $n === 0 ? ' class="active" aria-current="true"' : ''; ?> aria-label="<?php echo (int) ($n + 1); ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($images as $n => $image) : ?>
                        <div class="carousel-item<?php echo $n === 0 ? ' active' : ''; ?>">
                            <a class="d-block text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                                <div class="ratio ratio-1x1 bg-body-tertiary">
                                    <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($image->url); ?>" alt="<?php echo $escape($image->alt); ?>"<?php echo $n === 0 ? '' : ' loading="lazy"'; ?> decoding="async">
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
            <div class="ratio ratio-1x1 overflow-hidden rounded-top bg-body-tertiary">
                <?php if ($images) : ?>
                    <a class="d-block w-100 h-100 text-reset" href="<?php echo $escape($productUrl); ?>" aria-label="<?php echo $escape($item->title); ?>">
                        <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($images[0]->url); ?>" alt="<?php echo $escape($images[0]->alt); ?>" loading="lazy" decoding="async">
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
    </div>
    <div class="card-body">
        <?php if ($categoryTitle !== '') : ?>
            <div class="small text-body-secondary mb-1 dharmaCardCategory"><a class="link-secondary text-decoration-none" href="<?php echo $escape($displayData['categoryUrl'] ?? '#'); ?>"><?php echo $escape($categoryTitle); ?></a></div>
        <?php endif; ?>
        <h3 class="h5 card-title mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($productUrl); ?>"><?php echo $escape($item->title); ?></a></h3>
        <?php if (!empty($displayData['showIntro']) && $intro !== '') : ?>
            <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $displayData['fields'] ?? $item->fields ?? [], 'context' => 'card']); ?>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-end gap-2 dharmaCardFooter">
        <div class="dharmaCardPrice">
            <?php if ($price !== '') : ?>
                <div class="fw-bold fs-5 lh-sm"><?php echo $escape($price); ?></div>
                <?php if ($oldPrice !== '' && $showDiscount) : ?>
                    <div class="small text-body-secondary">
                        <s><?php echo $escape($oldPrice); ?></s>
                        <?php if ($discount > 0 && ($discountDisplay !== 'amount' || $discountAmount !== '')) : ?><span class="badge text-bg-danger ms-1"><?php echo $escape($discountText); ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="fw-semibold"><?php echo $escape(Text::_('COM_DHARMASHOP_PRODUCT_PRICE_REQUEST')); ?></div>
            <?php endif; ?>
            <?php if ((int) $item->in_stock === 0) : ?>
                <div class="small text-body-secondary"><?php echo $escape(Text::_('COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK')); ?></div>
            <?php endif; ?>
        </div>
        <?php // Справа: виджет количества (настройка «Выводить виджет количества в карточках») и кнопка «В корзину»; в режиме каталога их нет. ?>
        <?php if (empty($displayData['catalogMode']) && $price !== '' && (int) $item->in_stock !== 0) : ?>
            <div class="d-flex align-items-center justify-content-end gap-2<?php echo !empty($displayData['showQty']) ? ' w-100' : ''; ?> dharmaCardActions">
                <?php if (!empty($displayData['showQty'])) : ?>
                    <?php $step = (float) ($item->qty_step ?: 1); $min = (float) ($item->qty_min ?: $step); ?>
                    <div class="input-group input-group-sm flex-nowrap w-auto flex-shrink-0 dharmaCardQty" data-dharmashop-qty>
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="down" aria-label="−" title="−">−</button>
                        <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" value="<?php echo $escape((string) $min); ?>" data-min="<?php echo $escape((string) $min); ?>" data-step="<?php echo $escape((string) $step); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="up" aria-label="+" title="+">+</button>
                    </div>
                <?php endif; ?>
                <?php $cartText = (string) ($displayData['cartText'] ?? '') !== '' ? (string) $displayData['cartText'] : Text::_('COM_DHARMASHOP_ADD_TO_CART'); ?>
                <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1<?php echo !empty($displayData['showQty']) ? ' flex-grow-1' : ''; ?> dharmaCardCart" type="button" data-dharmashop-add-to-cart="<?php echo (int) $item->id; ?>" title="<?php echo $escape($cartText); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/><path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
                    <span><?php echo $escape($cartText); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</article>
