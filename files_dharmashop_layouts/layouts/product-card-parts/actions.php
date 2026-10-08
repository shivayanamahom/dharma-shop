<?php
/** Общая часть карточек товара: виджет количества и кнопка «В корзину» (в режиме каталога не выводятся). */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$price = (string) ($displayData['priceRange'] ?? '');

?>
        <?php if (empty($displayData['catalogMode']) && $price !== '' && (int) $item->in_stock !== 0) : ?>
            <div class="d-flex align-items-center justify-content-end gap-2<?php echo !empty($displayData['showQty']) ? ' w-100' : ''; ?> dharmaCardActions">
                <?php if (!empty($displayData['showQty'])) : ?>
                    <?php $step = (float) ($item->qty_step ?: 1); $min = (float) ($item->qty_min ?: $step); ?>
                    <div class="input-group input-group-sm flex-nowrap w-auto flex-shrink-0 dharmaCardQty" data-dharmashop-qty>
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="down" aria-label="−" title="−">−</button>
                        <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" value="<?php echo $escape((string) $min); ?>" data-min="<?php echo $escape((string) $min); ?>" data-step="<?php echo $escape((string) $step); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                        <button class="btn btn-outline-secondary" type="button" data-dharmashop-qty-step="up" aria-label="+" title="+">+</button>
                    </div>
                <?php endif; ?>
                <?php $cartText = (string) ($displayData['cartText'] ?? '') !== '' ? (string) $displayData['cartText'] : Text::_('COM_DHARMASHOP_ADD_TO_CART'); ?>
                <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1<?php echo !empty($displayData['showQty']) ? ' flex-grow-1' : ''; ?> dharmaCardCart" type="button" data-dharmashop-add-to-cart="<?php echo (int) $item->id; ?>" title="<?php echo $escape($cartText); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/><path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
                    <span><?php echo $escape($cartText); ?></span>
                </button>
            </div>
        <?php endif; ?>
