<?php
/**
 * Общая часть карточек товара и страницы товара: действия покупки (в режиме каталога не выводятся). Режим — `cartMode`:
 *  button  — только кнопка «В корзину»;
 *  qty     — поле количества «−» «+» и кнопка;
 *  stepper — кнопка, которая после добавления заменяется виджетом (слева убрать, число, «+»); для товара, уже лежащего в корзине
 *            (`inCartQty` > 0), сразу выводится виджет. Состоянием управляет cart.js.
 * Остальные параметры: priceRange, cartText, compactActions (таблица), stackActions (вертикально, страница товара),
 * justify (start|end), item (id, in_stock, qty_min, qty_step).
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$price = (string) ($displayData['priceRange'] ?? '');
// compactActions (таблица): без растягивания; на узких экранах нет виджета количества и подписи кнопки (остаётся значок).
$compact = !empty($displayData['compactActions']);
$stack = !empty($displayData['stackActions']) && !$compact;
$mode = in_array($displayData['cartMode'] ?? 'button', ['button', 'qty', 'stepper'], true) ? $displayData['cartMode'] : 'button';
$step = (float) ($item->qty_step ?: 1);
$min = (float) ($item->qty_min ?: $step);
$inCart = (float) ($displayData['inCartQty'] ?? 0);
$justify = ($displayData['justify'] ?? 'end') === 'start' ? 'start' : 'end';
$cartText = (string) ($displayData['cartText'] ?? '') !== '' ? (string) $displayData['cartText'] : Text::_('COM_DHARMASHOP_ADD_TO_CART');
$wide = $stack || ($mode === 'qty' && !$compact) || $mode === 'stepper';
?>
        <?php if (empty($displayData['catalogMode']) && $price !== '' && (int) $item->in_stock !== 0) : ?>
            <div class="d-flex <?php echo $stack ? 'flex-column align-items-stretch' : 'align-items-center'; ?> justify-content-<?php echo $justify; ?> gap-2<?php echo $wide ? ' w-100' : ''; ?> dharmaCardActions"<?php if ($mode === 'stepper') : ?> data-dharmashop-cart-control data-mode="stepper" data-product="<?php echo (int) $item->id; ?>" data-step="<?php echo $escape((string) $step); ?>" data-min="<?php echo $escape((string) $min); ?>" data-qty="<?php echo $escape((string) $inCart); ?>" data-item="<?php echo (int) ($displayData['inCartItem'] ?? 0); ?>"<?php endif; ?>>
                <?php if ($mode === 'qty') : ?>
                    <div class="input-group input-group-sm flex-nowrap w-auto flex-shrink-0<?php echo $stack ? ' align-self-start' : ''; ?><?php echo $compact ? ' d-none d-md-flex' : ''; ?> dharmaCardQty" data-dharmashop-qty>
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="down" aria-label="−" title="−">−</button>
                        <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" value="<?php echo $escape((string) $min); ?>" data-min="<?php echo $escape((string) $min); ?>" data-step="<?php echo $escape((string) $step); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="up" aria-label="+" title="+">+</button>
                    </div>
                <?php endif; ?>
                <button class="btn <?php echo $stack ? 'btn-primary bg-primary text-white w-100 py-2' : 'btn-outline-primary'; ?> btn-sm d-inline-flex align-items-center justify-content-center gap-1<?php echo $wide && !$stack ? ' flex-grow-1' : ''; ?><?php echo $mode === 'stepper' && $inCart > 0 ? ' d-none' : ''; ?> dharmaCardCart" type="button" data-dharmashop-add-to-cart="<?php echo (int) $item->id; ?>" title="<?php echo $escape($cartText); ?>" aria-label="<?php echo $escape($cartText); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/><path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
                    <span class="<?php echo $compact ? 'd-none d-md-inline' : ''; ?>"><?php echo $escape($cartText); ?></span>
                </button>
                <?php if ($mode === 'stepper' && $inCart > 0) : ?>
                    <?php echo (new FileLayout('components.dharmashop.product-card-parts.stepper', null, ['component' => 'none', 'client' => 0]))->render(['qty' => $inCart, 'step' => $step, 'min' => $min]); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
