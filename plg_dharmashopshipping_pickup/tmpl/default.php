<?php
/**
 * Вариант способа «Самовывоз» на странице оформления: один или несколько пунктов выдачи (складов) с контактами и картой.
 * Переопределение в шаблоне сайта: templates/<шаблон>/html/plg_dharmashopshipping_pickup/default.php
 *
 * @var  array  $displayData  method (Method), quote (Quote), selected (bool), id (int), errors (array), info (строки describe),
 *                            points (пункты: key, name, address, rows, map_url — ссылка общей карты с центром на пункте), point_selected (ключ выбранного пункта),
 *                            map_url (ссылка карты выбранного пункта или ''), map (mode: js|iframe|none, ratio, config — JSON для скрипта карты),
 *                            image_url, price_text, total_text, free_text (строки)
 *
 * Правила для всех типов доставки (docs/SHIPPING.md): радиокнопка `shipping_method` с data-shipping-radio, data-price-text, data-total-text;
 * блоки data-shipping-extra скрываются у невыбранных способов; выбор пункта — радио `shipping[<id>][point]` с data-shipping-point
 * (data-summary-label/-value/-rows дублируют выбор в боковой панели, data-map-url — адрес карты пункта), сведения пункта — блок data-shipping-extra
 * data-shipping-point-detail="<ключ>". Карта одна на способ: iframe с data-shipping-map внутри блока data-shipping-map-wrap (JS меняет src при выборе пункта).
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$method = $displayData['method'];
$quote = $displayData['quote'];
$selected = (bool) $displayData['selected'];
$id = (int) $displayData['id'];
$errors = (array) ($displayData['errors'] ?? []);
$points = (array) $displayData['points'];
$many = count($points) > 1;
$activePoint = (string) $displayData['point_selected'];
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$priceText = $quote->free ? $displayData['free_text'] : $displayData['price_text'];
?>
<div class="border rounded p-3 dharmaShippingOption" data-shipping-option>
    <div class="form-check mb-0">
        <input class="form-check-input" type="radio" name="shipping_method" id="dharma-shipping-<?php echo $id; ?>" value="<?php echo $id; ?>"<?php echo $selected ? ' checked' : ''; ?>
            data-shipping-radio data-price-text="<?php echo $esc($priceText); ?>" data-total-text="<?php echo $esc($displayData['total_text']); ?>">
        <label class="form-check-label w-100 d-flex justify-content-between align-items-baseline gap-3" for="dharma-shipping-<?php echo $id; ?>">
            <span class="d-flex align-items-center gap-2">
                <?php if (!empty($displayData['image_url'])) : ?><img src="<?php echo $esc($displayData['image_url']); ?>" alt="" width="32" height="32" class="flex-shrink-0 object-fit-contain" loading="lazy"><?php endif; ?>
                <span>
                    <span class="fw-semibold"><?php echo $esc($method->title); ?></span>
                    <?php if ($quote->term !== '') : ?><span class="d-block small text-body-secondary"><?php echo $esc($quote->term); ?></span><?php endif; ?>
                </span>
            </span>
            <span class="fw-semibold text-nowrap<?php echo $quote->free ? ' text-success' : ''; ?>"><?php echo $esc($priceText); ?></span>
        </label>
    </div>
    <?php if ($method->description !== '') : ?>
        <div class="small text-body-secondary mt-2 ms-4 text-break"><?php echo nl2br($esc($method->description)); ?></div>
    <?php endif; ?>
    <?php if ($many) : ?>
        <div class="mt-2 ms-3 d-flex flex-column gap-2<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra role="radiogroup" aria-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT')); ?>">
            <?php foreach ($points as $point) :
                $pointId = 'dharma-shipping-' . $id . '-point-' . $point['key'];
                ?>
                <div class="form-check mb-0">
                    <input class="form-check-input<?php echo isset($errors['point']) ? ' is-invalid' : ''; ?>" type="radio" name="shipping[<?php echo $id; ?>][point]" id="<?php echo $esc($pointId); ?>" value="<?php echo $esc($point['key']); ?>"<?php echo $point['key'] === $activePoint ? ' checked' : ''; ?>
                        data-shipping-point data-summary-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT')); ?>" data-summary-value="<?php echo $esc($point['name']); ?>" data-summary-rows="<?php echo $esc(json_encode($point['rows'], JSON_UNESCAPED_UNICODE)); ?>" data-map-url="<?php echo $esc($point['map_url']); ?>">
                    <label class="form-check-label" for="<?php echo $esc($pointId); ?>">
                        <span class="fw-semibold"><?php echo $esc($point['name']); ?></span>
                        <span class="d-block small text-body-secondary"><?php echo $esc($point['address']); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
            <?php if (isset($errors['point'])) : ?><div class="invalid-feedback d-block"><?php echo $esc(Text::_($errors['point'])); ?></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php foreach ($points as $point) :
        $visible = $selected && (!$many || $point['key'] === $activePoint);
        ?>
        <div class="mt-2 ms-3<?php echo $visible ? '' : ' d-none'; ?>" data-shipping-extra<?php echo $many ? ' data-shipping-point-detail="' . $esc($point['key']) . '"' : ''; ?>>
            <?php if ($point['rows']) : ?>
                <dl class="row small mb-0 dharmaShippingInfo">
                    <?php foreach ($point['rows'] as $row) : ?>
                        <dt class="col-sm-4 fw-normal text-body-secondary"><?php echo $esc($row['label']); ?></dt>
                        <dd class="col-sm-8 mb-1 text-break"><?php echo nl2br($esc($row['value'])); ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php $map = (array) $displayData['map']; $ratio = 'ratio-' . preg_replace('/[^0-9x]/', '', (string) $map['ratio']); ?>
    <?php if ($map['mode'] === 'js') : ?>
        <?php // Яндекс Карты по ключу API: карту строит скрипт плагина по data-config. ?>
        <div class="mt-2 ms-3<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra>
            <div class="ratio <?php echo $esc($ratio); ?> rounded overflow-hidden border">
                <div class="w-100 h-100" data-pickup-map data-config="<?php echo $esc($map['config']); ?>" role="img" aria-label="<?php echo $esc(Text::sprintf('PLG_DHARMASHOPSHIPPING_PICKUP_MAP_TITLE', $method->title)); ?>"></div>
            </div>
        </div>
    <?php elseif ($map['mode'] === 'iframe' && ($displayData['map_url'] !== '' || array_filter(array_column($points, 'map_url')))) : ?>
        <?php // Простой виджет Яндекса без ключа API: адрес меняется при выборе пункта (data-map-url). ?>
        <div class="mt-2 ms-3<?php echo $selected && $displayData['map_url'] !== '' ? '' : ' d-none'; ?>" data-shipping-extra data-shipping-map-wrap>
            <div class="ratio <?php echo $esc($ratio); ?> rounded overflow-hidden border">
                <iframe src="<?php echo $esc($displayData['map_url']); ?>" title="<?php echo $esc(Text::sprintf('PLG_DHARMASHOPSHIPPING_PICKUP_MAP_TITLE', $method->title)); ?>"
                    loading="lazy" referrerpolicy="no-referrer" allowfullscreen data-shipping-map></iframe>
            </div>
        </div>
    <?php endif; ?>
</div>
