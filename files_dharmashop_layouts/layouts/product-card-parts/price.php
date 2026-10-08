<?php
/** Общая часть карточек товара: блок цены (текущая цена, обычная цена и скидка, «Нет в наличии»). */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

?>
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
