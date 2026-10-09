<?php
/**
 * Вариант способа «Курьер» на странице оформления. Переопределение в шаблоне сайта:
 * templates/<шаблон>/html/plg_dharmashopshipping_courier/default.php
 *
 * @var  array  $displayData  method (Method), quote (Quote), selected (bool), id (int), values (array), errors (array [ключ поля => языковой ключ]),
 *                            info (строки describe), base_fields (неизменяемые поля: city, street, house — key, label, col), fields (дополнительные поля
 *                            конструктора: key, label, type, required, width, placeholder, options), distance_mode (цена по расстоянию),
 *                            suggest (подсказки адреса), map (mode: js|none, ratio, config — JSON для скрипта карты), pending (цена ещё не рассчитана),
 *                            ajax_url, image_url, price_text, total_text, free_text (строки)
 *
 * Правила для всех типов доставки (docs/SHIPPING.md): макет типа выводит только панель выбранного способа; плитку-вкладку с радиокнопкой `shipping_method`
 * (data-shipping-radio, data-price-text, data-total-text, цена — [data-shipping-option-price]) рисует страница оформления (default_shipping_tile.php); корень панели — [data-shipping-option] с data-method-id.
 * Поля покупателя — `shipping[<id>][<ключ>]` в блоке data-shipping-extra, у каждого data-summary-label. Город, улица и дом — всегда:
 * [data-courier-city], [data-courier-street] и [data-courier-house]; одна строка адреса с подсказками — [data-courier-search] и список [data-courier-suggest]; контейнер [data-courier]
 * (data-ajax-url, data-method-id, data-suggest, data-distance, data-apikey — публичный ключ для подсказок в браузере, data-near — «широта,долгота» центра складов), статус расчёта — [data-courier-status], карта — [data-courier-map].
 * Скрипт плагина обновляет цену способа (data-shipping-option-price, data-price-text/-total-text радио) и событием
 * dharmashop:shipping-quote сообщает об этом оформлению.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$method = $displayData['method'];
$quote = $displayData['quote'];
$selected = (bool) $displayData['selected'];
$id = (int) $displayData['id'];
$errors = (array) ($displayData['errors'] ?? []);
$values = (array) ($displayData['values'] ?? []);
$fields = (array) ($displayData['fields'] ?? []);
$map = (array) ($displayData['map'] ?? ['mode' => 'none']);
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Подсказки или расчёт по расстоянию: адрес вводится одной строкой, город, улица и дом хранятся в скрытых полях.
$single = (bool) ($displayData['suggest'] || $displayData['distance_mode']);
$addressError = '';
foreach (['city', 'street', 'house'] as $baseKey) {
    if ($addressError === '' && isset($errors[$baseKey])) {
        $addressError = (string) $errors[$baseKey];
    }
}
?>
<div class="border rounded p-3 mt-2 dharmaShippingOption<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-option data-method-id="<?php echo $id; ?>">
    <?php if ($method->description !== '') : ?>
        <div class="small text-body-secondary mb-2 text-break"><?php echo nl2br($esc($method->description)); ?></div>
    <?php endif; ?>
    <?php if ($quote->note !== '') : ?>
        <div class="small text-body-secondary mb-2 text-break"><?php echo nl2br($esc($quote->note)); ?></div>
    <?php endif; ?>
    <div class="mt-1<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra data-courier
        data-ajax-url="<?php echo $esc($displayData['ajax_url'] ?? ''); ?>" data-method-id="<?php echo $id; ?>"
        data-suggest="<?php echo $displayData['suggest'] ? '1' : '0'; ?>" data-distance="<?php echo $displayData['distance_mode'] ? '1' : '0'; ?>"
        data-apikey="<?php echo $esc($displayData['suggest_key'] ?? ''); ?>" data-near="<?php echo $esc($displayData['suggest_near'] ?? ''); ?>">
        <?php if ($single) : ?>
            <?php // Одна строка адреса: подсказка (или точка на карте) определяет город, улицу и дом (скрытые поля ниже) и запускает расчёт. ?>
            <div class="position-relative mb-2">
                <label class="form-label small mb-1" for="dharma-shipping-<?php echo $id; ?>-search"><?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_SEARCH_LABEL')); ?> <span class="text-danger" aria-hidden="true">*</span></label>
                <input class="form-control<?php echo $addressError !== '' ? ' is-invalid' : ''; ?>" type="text" id="dharma-shipping-<?php echo $id; ?>-search" data-courier-search maxlength="255" autocomplete="off"
                    placeholder="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_SEARCH_PLACEHOLDER')); ?>">
                <?php if ($addressError !== '') : ?><div class="invalid-feedback d-block"><?php echo $esc(Text::_($addressError)); ?></div><?php endif; ?>
                <div class="list-group position-absolute start-0 end-0 mx-0 shadow-sm z-3 d-none" data-courier-suggest role="listbox" aria-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_SUGGEST_LABEL')); ?>"></div>
                <?php if ($map['mode'] === 'js') : ?><div class="form-text"><?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_MAP_HINT')); ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="row g-2">
            <?php // Город, улица, дом — неизменяемые обязательные поля: по ним определяется точка доставки. С подсказками они скрыты (заполняются строкой адреса), без них — обычные поля. ?>
            <?php foreach ((array) $displayData['base_fields'] as $base) :
                $key = (string) $base['key'];
                $fieldId = 'dharma-shipping-' . $id . '-' . $key;
                $invalid = isset($errors[$key]);
                if ($single) : ?>
                    <input type="hidden" id="<?php echo $esc($fieldId); ?>" name="shipping[<?php echo $id; ?>][<?php echo $esc($key); ?>]" value="<?php echo $esc((string) ($values[$key] ?? '')); ?>"
                        data-courier-<?php echo $esc($key); ?><?php echo $key === 'house' ? ' data-summary-label="' . $esc($base['label']) . '"' : ''; ?>>
                    <?php continue;
                endif;
                ?>
                <div class="<?php echo $esc($base['col']); ?> position-relative">
                    <label class="form-label small mb-1" for="<?php echo $esc($fieldId); ?>"><?php echo $esc($base['label']); ?> <span class="text-danger" aria-hidden="true">*</span></label>
                    <input class="form-control<?php echo $invalid ? ' is-invalid' : ''; ?>" type="text" id="<?php echo $esc($fieldId); ?>" name="shipping[<?php echo $id; ?>][<?php echo $esc($key); ?>]"
                        value="<?php echo $esc((string) ($values[$key] ?? '')); ?>" maxlength="<?php echo $key === 'house' ? 20 : 255; ?>" data-summary-label="<?php echo $esc($base['label']); ?>" data-courier-<?php echo $esc($key); ?> autocomplete="off">
                    <?php if ($invalid) : ?><div class="invalid-feedback d-block"><?php echo $esc(Text::_($errors[$key])); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($fields as $field) :
                $key = (string) $field['key'];
                $fieldId = 'dharma-shipping-' . $id . '-' . $key;
                $name = 'shipping[' . $id . '][' . $key . ']';
                $value = (string) ($values[$key] ?? '');
                $invalid = isset($errors[$key]);
                $class = 'form-control' . ($invalid ? ' is-invalid' : '');
                $attrs = ' id="' . $esc($fieldId) . '" name="' . $esc($name) . '" data-summary-label="' . $esc($field['label']) . '"'
                    . ($field['placeholder'] !== '' ? ' placeholder="' . $esc($field['placeholder']) . '"' : '');
                ?>
                <div class="<?php echo $field['width'] === 'half' ? 'col-md-6' : 'col-12'; ?>">
                    <label class="form-label small mb-1" for="<?php echo $esc($fieldId); ?>"><?php echo $esc($field['label']); ?><?php echo (int) $field['required'] === 1 ? ' <span class="text-danger" aria-hidden="true">*</span>' : ''; ?></label>
                    <?php if ($field['type'] === 'textarea') : ?>
                        <textarea class="<?php echo $class; ?>"<?php echo $attrs; ?> rows="2" maxlength="1000"><?php echo $esc($value); ?></textarea>
                    <?php elseif ($field['type'] === 'select') : ?>
                        <select class="form-select<?php echo $invalid ? ' is-invalid' : ''; ?>"<?php echo $attrs; ?>>
                            <option value=""><?php echo $esc($field['placeholder'] !== '' ? $field['placeholder'] : Text::_('PLG_DHARMASHOPSHIPPING_COURIER_SELECT')); ?></option>
                            <?php foreach ((array) $field['options'] as $option) : ?>
                                <option value="<?php echo $esc($option); ?>"<?php echo $option === $value ? ' selected' : ''; ?>><?php echo $esc($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input class="<?php echo $class; ?>" type="<?php echo $field['type'] === 'tel' ? 'tel' : 'text'; ?>"<?php echo $attrs; ?> value="<?php echo $esc($value); ?>" maxlength="255">
                    <?php endif; ?>
                    <?php if ($invalid) : ?><div class="invalid-feedback d-block"><?php echo $esc(Text::_($errors[$key])); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($displayData['distance_mode']) : ?>
            <div class="small mt-2 text-body-secondary" data-courier-status data-progress="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_CALCULATING')); ?>" role="status" aria-live="polite"></div>
        <?php endif; ?>
        <?php if ($map['mode'] === 'js') : ?>
            <div class="ratio ratio-<?php echo $esc(preg_replace('/[^0-9x]/', '', (string) $map['ratio'])); ?> rounded overflow-hidden border mt-2">
                <div class="w-100 h-100" data-courier-map data-config="<?php echo $esc($map['config']); ?>" role="img" aria-label="<?php echo $esc(Text::_('PLG_DHARMASHOPSHIPPING_COURIER_MAP_LABEL')); ?>"></div>
            </div>
        <?php endif; ?>
    </div>
</div>
