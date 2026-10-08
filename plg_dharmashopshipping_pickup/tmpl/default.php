<?php
/**
 * Вариант способа «Самовывоз» на странице оформления: пункт выдачи, контакты и карта. Переопределение в шаблоне сайта:
 * templates/<шаблон>/html/plg_dharmashopshipping_pickup/default.php
 *
 * @var  array  $displayData  method (Method), quote (Quote), selected (bool), id (int), info (строки describe), map_url (адрес карты или ''),
 *                            price_text, total_text, free_text (строки)
 *
 * Правила для всех типов доставки (docs/SHIPPING.md): радиокнопка `shipping_method` с data-shipping-radio, data-price-text, data-total-text;
 * блок data-shipping-extra скрывается у невыбранных способов (карта показывается только у выбранного).
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$method = $displayData['method'];
$quote = $displayData['quote'];
$selected = (bool) $displayData['selected'];
$id = (int) $displayData['id'];
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$priceText = $quote->free ? $displayData['free_text'] : $displayData['price_text'];
?>
<div class="border rounded p-3 dharmaShippingOption" data-shipping-option>
    <div class="form-check mb-0">
        <input class="form-check-input" type="radio" name="shipping_method" id="dharma-shipping-<?php echo $id; ?>" value="<?php echo $id; ?>"<?php echo $selected ? ' checked' : ''; ?>
            data-shipping-radio data-price-text="<?php echo $esc($priceText); ?>" data-total-text="<?php echo $esc($displayData['total_text']); ?>">
        <label class="form-check-label w-100 d-flex justify-content-between align-items-baseline gap-3" for="dharma-shipping-<?php echo $id; ?>">
            <span>
                <span class="fw-semibold"><?php echo $esc($method->title); ?></span>
                <?php if ($quote->term !== '') : ?><span class="d-block small text-body-secondary"><?php echo $esc($quote->term); ?></span><?php endif; ?>
            </span>
            <span class="fw-semibold text-nowrap<?php echo $quote->free ? ' text-success' : ''; ?>"><?php echo $esc($priceText); ?></span>
        </label>
    </div>
    <?php if ($method->description !== '') : ?>
        <div class="small text-body-secondary mt-2 ms-4 text-break"><?php echo nl2br($esc($method->description)); ?></div>
    <?php endif; ?>
    <?php if ($displayData['info']) : ?>
        <dl class="row small mb-0 mt-2 ms-3 dharmaShippingInfo">
            <?php foreach ($displayData['info'] as $row) : ?>
                <dt class="col-sm-4 fw-normal text-body-secondary"><?php echo $esc($row['label']); ?></dt>
                <dd class="col-sm-8 mb-1 text-break"><?php echo nl2br($esc($row['value'])); ?></dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
    <?php if ($displayData['map_url'] !== '') : ?>
        <div class="mt-2 ms-3<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra>
            <div class="ratio ratio-16x9 rounded overflow-hidden border">
                <iframe src="<?php echo $esc($displayData['map_url']); ?>" title="<?php echo $esc(Text::sprintf('PLG_DHARMASHOPSHIPPING_PICKUP_MAP_TITLE', $method->title)); ?>"
                    loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe>
            </div>
        </div>
    <?php endif; ?>
</div>
