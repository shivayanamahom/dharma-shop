<?php
/**
 * Предупреждение о недоступных позициях корзины (страница корзины и оформление заказа): список позиций с причиной и кнопки
 * «Удалить недоступные» и «Уменьшить до наличия». Кнопки отправляют POST на `cart.removeUnavailable` (токен — из формы, в которой стоит блок,
 * либо из отдельной формы с id `formId`: на оформлении блок лежит внутри формы заказа, а вложенных форм в HTML нет).
 *
 * displayData: lines (позиции корзины), action (адрес задачи), formId ('' — кнопки используют formaction своей формы), return (cart|checkout).
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Cart\CartService;
use Joomla\CMS\Language\Text;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$bad = array_values(array_filter((array) ($displayData['lines'] ?? []), static fn (object $line): bool => (string) $line->error !== ''));

if (!$bad) {
    return;
}

$reducible = array_filter($bad, static fn (object $line): bool => CartService::reducedQty($line) !== null);
$formId = (string) ($displayData['formId'] ?? '');
$attributes = static fn (string $mode): string => ($formId !== '' ? ' form="' . $escape($formId) . '"' : ' formaction="' . $escape((string) ($displayData['action'] ?? '')) . '" formmethod="post"')
    . ' name="mode" value="' . $mode . '" formnovalidate';
?>
<div class="alert alert-warning small mb-3 dharmaCartUnavailable" role="alert">
    <div class="fw-semibold mb-1"><?php echo Text::_('COM_DHARMASHOP_CART_UNAVAILABLE_TITLE'); ?></div>
    <div class="mb-2"><?php echo Text::_('COM_DHARMASHOP_CART_UNAVAILABLE_HINT'); ?></div>
    <ul class="mb-2 ps-3">
        <?php foreach ($bad as $line) : ?>
            <?php $reduced = CartService::reducedQty($line); ?>
            <li><?php echo $escape($line->title); ?>: <?php echo $escape(Text::_((string) $line->error)); ?><?php echo $reduced !== null ? ' ' . $escape(Text::sprintf('COM_DHARMASHOP_CART_AVAILABLE_ONLY', rtrim(rtrim(number_format($reduced, 3, '.', ''), '0'), '.'))) : ''; ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-sm btn-warning"<?php echo $attributes('remove'); ?>><?php echo Text::_('COM_DHARMASHOP_CART_REMOVE_UNAVAILABLE'); ?></button>
        <?php if ($reducible) : ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary"<?php echo $attributes('reduce'); ?>><?php echo Text::_('COM_DHARMASHOP_CART_REDUCE_TO_STOCK'); ?></button>
        <?php endif; ?>
    </div>
</div>
