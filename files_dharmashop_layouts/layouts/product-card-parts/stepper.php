<?php
/**
 * Виджет количества «после добавления» (режим «кнопка → виджет»): слева «убрать» (значок корзины; «−», пока можно убавить на шаг),
 * по центру количество, справа «+». Выводится сервером для товаров, уже лежащих в корзине, и один раз как <template> —
 * из него cart.js создаёт виджет после добавления. Действия и подписи значков переключает cart.js (data-атрибуты).
 * displayData: qty (текущее количество), step, min.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$qty = (float) ($displayData['qty'] ?? 1);
$step = (float) ($displayData['step'] ?? 1) ?: 1.0;
$min = (float) ($displayData['min'] ?? $step) ?: $step;
$last = $qty - $step < $min - 1e-9;
$format = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
?>
<div class="input-group input-group-sm flex-nowrap w-100 dharmaCardStepper" data-dharmashop-stepper>
    <button class="btn btn-outline-secondary" type="button" data-dharmashop-stepper-minus aria-label="<?php echo $escape(Text::_($last ? 'COM_DHARMASHOP_CART_REMOVE' : 'COM_DHARMASHOP_CART_MINUS')); ?>" title="<?php echo $escape(Text::_($last ? 'COM_DHARMASHOP_CART_REMOVE' : 'COM_DHARMASHOP_CART_MINUS')); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false" data-dharmashop-stepper-trash<?php echo $last ? '' : ' class="d-none"'; ?>><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
        <span data-dharmashop-stepper-minus-sign<?php echo $last ? ' class="d-none"' : ''; ?> aria-hidden="true">−</span>
    </button>
    <span class="form-control text-center d-flex align-items-center justify-content-center px-1" data-dharmashop-stepper-qty role="status" aria-live="polite"><?php echo $escape($format($qty)); ?></span>
    <button class="btn btn-outline-secondary" type="button" data-dharmashop-stepper-plus aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>" title="<?php echo $escape(Text::_('COM_DHARMASHOP_CART_PLUS')); ?>">+</button>
</div>
