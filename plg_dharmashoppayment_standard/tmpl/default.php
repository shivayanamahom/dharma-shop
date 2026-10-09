<?php
/**
 * Панель способа «Стандартная оплата» на странице оформления. Переопределение в шаблоне сайта:
 * templates/<шаблон>/html/plg_dharmashoppayment_standard/default.php
 *
 * @var  array  $displayData  method (Method), selected (bool), id (int), values (array), errors (array), info (строки describe), image_url (адрес изображения или '')
 *
 * Правила для всех типов оплаты (docs/PAYMENTS.md): макет типа выводит только панель выбранного способа; плитку с радиокнопкой `payment_method` рисует
 * страница оформления (default_payment_tile.php). Корень панели — [data-payment-option] с data-method-id (у невыбранных способов скрыт). Поля покупателя —
 * `payment[<id>][<ключ>]` с data-summary-label; текст-инструкция попадает в заказ и письмо через describe().
 */
\defined('_JEXEC') or die;

$method = $displayData['method'];
$selected = (bool) $displayData['selected'];
$id = (int) $displayData['id'];
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="border rounded p-3 mt-2 dharmaPaymentOption<?php echo $selected ? '' : ' d-none'; ?>" data-payment-option data-method-id="<?php echo $id; ?>">
    <?php if ($method->description !== '') : ?>
        <div class="small text-body-secondary mb-2 text-break"><?php echo nl2br($esc($method->description)); ?></div>
    <?php endif; ?>
    <?php foreach ((array) $displayData['info'] as $row) : ?>
        <div class="small text-break"><?php echo nl2br($esc($row['value'])); ?></div>
    <?php endforeach; ?>
</div>
