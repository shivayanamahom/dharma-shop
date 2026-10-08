<?php
/**
 * Вариант способа «Стандартная доставка» на странице оформления. Переопределение в шаблоне сайта:
 * templates/<шаблон>/html/plg_dharmashopshipping_standard/default.php
 *
 * @var  array  $displayData  method (Method), quote (Quote), selected (bool), id (int), values (array), errors (array [ключ поля => языковой ключ]),
 *                            info (строки describe), fields (определения полей покупателя), price_text, total_text, free_text (строки)
 *
 * Правила для всех типов доставки (docs/SHIPPING.md): радиокнопка `shipping_method` со значением id способа и атрибутами
 * data-shipping-radio, data-price-text, data-total-text; поля покупателя — `shipping[<id>][<ключ>]` внутри блока data-shipping-extra
 * (он скрывается у невыбранных способов), у каждого поля data-summary-label — подпись для боковой панели.
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
    <?php if ($fields) : ?>
        <div class="row g-2 mt-1 ms-3<?php echo $selected ? '' : ' d-none'; ?>" data-shipping-extra>
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
                            <option value=""><?php echo $esc($field['placeholder'] !== '' ? $field['placeholder'] : Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_SELECT')); ?></option>
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
    <?php endif; ?>
</div>
