<?php
/**
 * Вариант способа «Самовывоз» на странице оформления: один или несколько пунктов выдачи (складов) с контактами и картой.
 * Переопределение в шаблоне сайта: templates/<шаблон>/html/plg_dharmashopshipping_pickup/default.php
 *
 * @var  array  $displayData  method (Method), quote (Quote), selected (bool), id (int), errors (array), info (строки describe),
 *                            points (пункты: key, name, address, rows, detail_rows — без адреса, map_url — ссылка общей карты с центром на пункте), point_selected (ключ выбранного пункта),
 *                            slider (true — карточки в слайдере Swiper),
 *                            map_url (ссылка карты выбранного пункта или ''), map (mode: js|iframe|none, ratio, config — JSON для скрипта карты),
 *                            image_url, price_text, total_text, free_text (строки)
 *
 * Правила для всех типов доставки (docs/SHIPPING.md): радиокнопка `shipping_method` с data-shipping-radio, data-price-text, data-total-text.
 * Блок data-shipping-extra (карта и карточки пунктов) скрывается у невыбранных способов. Карточка пункта — [data-shipping-point-card] с радио
 * `shipping[<id>][point]` (data-shipping-point; data-summary-label/-value/-rows дублируют выбор в боковой панели, data-map-url — адрес карты пункта).
 * Карта одна на способ: контейнер [data-pickup-map] (скрипт карты) либо iframe [data-shipping-map] внутри [data-shipping-map-wrap].
 * Клик по метке выбирает карточку, выбор карточки центрирует метку.
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
    <?php if ($points) : ?>
        <?php // Карта сверху, под ней карточки пунктов: клик по метке выбирает карточку, клик по карточке центрирует метку (скрипт карты слушает change радио). ?>
        <?php $map = (array) $displayData['map']; $ratio = 'ratio-' . preg_replace('/[^0-9x]/', '', (string) $map['ratio']); ?>
        <div class="mt-3 ms-3<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra>
            <?php if ($map['mode'] === 'js') : ?>
                <div class="ratio <?php echo $esc($ratio); ?> rounded overflow-hidden border mb-3">
                    <div class="w-100 h-100" data-pickup-map data-config="<?php echo $esc($map['config']); ?>" role="img" aria-label="<?php echo $esc(Text::sprintf('PLG_DHARMASHOPSHIPPING_PICKUP_MAP_TITLE', $method->title)); ?>"></div>
                </div>
            <?php elseif ($map['mode'] === 'iframe' && ($displayData['map_url'] !== '' || array_filter(array_column($points, 'map_url')))) : ?>
                <?php // Простой виджет Яндекса без ключа API: адрес меняется при выборе пункта (data-map-url). ?>
                <div class="ratio <?php echo $esc($ratio); ?> rounded overflow-hidden border mb-3<?php echo $displayData['map_url'] !== '' ? '' : ' d-none'; ?>" data-shipping-map-wrap>
                    <iframe src="<?php echo $esc($displayData['map_url']); ?>" title="<?php echo $esc(Text::sprintf('PLG_DHARMASHOPSHIPPING_PICKUP_MAP_TITLE', $method->title)); ?>"
                        loading="lazy" referrerpolicy="no-referrer" allowfullscreen data-shipping-map></iframe>
                </div>
            <?php endif; ?>
            <?php
            $slider = !empty($displayData['slider']);
            $card = static function (array $point) use ($id, $many, $activePoint, $errors, $esc): string {
                $pointId = 'dharma-shipping-' . $id . '-point-' . $point['key'];
                $isActive = $point['key'] === $activePoint;
                ob_start();
                ?>
                <div class="card h-100 position-relative<?php echo $many && $isActive ? ' border-primary shadow-sm' : ''; ?>" data-shipping-point-card>
                    <div class="card-body p-3">
                        <?php if ($many) : ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input<?php echo isset($errors['point']) ? ' is-invalid' : ''; ?>" type="radio" name="shipping[<?php echo $id; ?>][point]" id="<?php echo $esc($pointId); ?>" value="<?php echo $esc($point['key']); ?>"<?php echo $isActive ? ' checked' : ''; ?>
                                    data-shipping-point data-summary-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT')); ?>" data-summary-value="<?php echo $esc($point['name']); ?>" data-summary-rows="<?php echo $esc(json_encode($point['rows'], JSON_UNESCAPED_UNICODE)); ?>" data-map-url="<?php echo $esc($point['map_url']); ?>">
                                <label class="form-check-label fw-semibold stretched-link" for="<?php echo $esc($pointId); ?>"><?php echo $esc($point['name']); ?></label>
                            </div>
                        <?php else : ?>
                            <div class="fw-semibold mb-1"><?php echo $esc($point['name']); ?></div>
                        <?php endif; ?>
                        <div class="small text-body-secondary text-break"><?php echo $esc($point['address']); ?></div>
                    </div>
                </div>
                <?php
                return (string) ob_get_clean();
            };
            ?>
            <?php if ($slider) : ?>
                <?php // Много пунктов: слайдер Swiper (скрипт плагина создаёт его, когда блок виден); стрелки — кнопки Bootstrap. ?>
                <div class="d-flex justify-content-end gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-pickup-prev aria-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_PREV')); ?>">&lsaquo;</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-pickup-next aria-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_NEXT')); ?>">&rsaquo;</button>
                </div>
                <div class="swiper" data-pickup-slider<?php echo $many ? ' role="radiogroup" aria-label="' . $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT')) . '"' : ''; ?>>
                    <div class="swiper-wrapper">
                        <?php foreach ($points as $point) : ?>
                            <div class="swiper-slide h-auto"><?php echo $card($point); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="row row-cols-1 row-cols-md-2 g-2"<?php echo $many ? ' role="radiogroup" aria-label="' . $esc(Text::_('PLG_DHARMASHOPSHIPPING_PICKUP_ROW_POINT')) . '"' : ''; ?>>
                    <?php foreach ($points as $point) : ?>
                        <div class="col"><?php echo $card($point); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($errors['point'])) : ?><div class="invalid-feedback d-block"><?php echo $esc(Text::_($errors['point'])); ?></div><?php endif; ?>
            <?php // Подробности выбранного пункта: телефон, часы работы, срок, как добраться (виден блок только выбранного пункта). ?>
            <?php foreach ($points as $point) : ?>
                <?php if ($point['detail_rows']) : ?>
                    <div class="card bg-body-tertiary border-0 mt-2<?php echo $many && $point['key'] !== $activePoint ? ' d-none' : ''; ?>" data-shipping-extra<?php echo $many ? ' data-shipping-point-detail="' . $esc($point['key']) . '"' : ''; ?>>
                        <div class="card-body p-3">
                            <dl class="row small mb-0 dharmaShippingInfo">
                                <?php foreach ($point['detail_rows'] as $row) : ?>
                                    <dt class="col-sm-4 fw-normal text-body-secondary"><?php echo $esc($row['label']); ?></dt>
                                    <dd class="col-sm-8 mb-1 text-break"><?php echo nl2br($esc($row['value'])); ?></dd>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
