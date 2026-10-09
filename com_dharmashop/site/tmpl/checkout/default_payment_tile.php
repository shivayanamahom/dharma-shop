<?php
/**
 * Плитка-вкладка способа оплаты на оформлении: радиокнопка Bootstrap (btn-check), выбранная плитка выделена. Панель выбранного способа
 * выводит макет плагина типа ниже ряда плиток. Переопределение: templates/<шаблон>/html/com_dharmashop/checkout/default_payment_tile.php
 *
 * @var  array  $this->paymentTile  id, selected, visible, image_url, title, info
 */
\defined('_JEXEC') or die;

$tile = $this->paymentTile;
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$id = (int) $tile['id'];
?>
<div class="col<?php echo $tile['visible'] ? '' : ' d-none'; ?>">
    <input class="btn-check" type="radio" name="payment_method" id="dharma-payment-<?php echo $id; ?>" value="<?php echo $id; ?>" autocomplete="off"<?php echo $tile['selected'] ? ' checked' : ''; ?><?php echo $tile['visible'] ? '' : ' disabled'; ?>
        data-payment-radio>
    <label class="btn btn-outline-primary w-100 h-100 d-flex align-items-center gap-2 p-3 text-start dharmaPaymentTile" for="dharma-payment-<?php echo $id; ?>">
        <?php if ($tile['image_url'] !== '') : ?><img src="<?php echo $esc($tile['image_url']); ?>" alt="" width="32" height="32" class="flex-shrink-0 object-fit-contain" loading="lazy"><?php endif; ?>
        <span class="fw-semibold lh-sm"><?php echo $esc($tile['title']); ?></span>
    </label>
</div>
