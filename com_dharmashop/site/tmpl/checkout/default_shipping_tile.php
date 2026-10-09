<?php
/**
 * Плитка-вкладка способа доставки на оформлении: радиокнопка Bootstrap (btn-check), выбранная плитка выделена. Панель выбранного способа
 * (описание, поля, карта) выводит макет плагина типа ниже ряда плиток. Переопределение: templates/<шаблон>/html/com_dharmashop/checkout/default_shipping_tile.php
 *
 * @var  array  $this->shippingTile  id, selected, free, image_url, total_text, title, term, price_text
 */
\defined('_JEXEC') or die;

$tile = $this->shippingTile;
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$id = (int) $tile['id'];
?>
<div class="col">
    <input class="btn-check" type="radio" name="shipping_method" id="dharma-shipping-<?php echo $id; ?>" value="<?php echo $id; ?>" autocomplete="off"<?php echo $tile['selected'] ? ' checked' : ''; ?>
        data-shipping-radio data-price-text="<?php echo $esc($tile['price_text']); ?>" data-total-text="<?php echo $esc($tile['total_text']); ?>">
    <label class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-start justify-content-between gap-2 p-3 text-start dharmaShippingTile" for="dharma-shipping-<?php echo $id; ?>">
        <span class="d-flex align-items-center gap-2 w-100">
            <?php if ($tile['image_url'] !== '') : ?><img src="<?php echo $esc($tile['image_url']); ?>" alt="" width="32" height="32" class="flex-shrink-0 object-fit-contain" loading="lazy"><?php endif; ?>
            <span class="fw-semibold lh-sm"><?php echo $esc($tile['title']); ?></span>
        </span>
        <span class="d-block w-100">
            <?php if ($tile['term'] !== '') : ?><span class="d-block small"><?php echo $esc($tile['term']); ?></span><?php endif; ?>
            <span class="d-block fw-semibold" data-shipping-option-price><?php echo $esc($tile['price_text']); ?></span>
        </span>
    </label>
</div>
