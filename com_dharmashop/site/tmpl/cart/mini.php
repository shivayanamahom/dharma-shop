<?php
/**
 * Мини-корзина для боковой панели модуля корзины (подгружается фрагментом): строки с количеством и удалением, итоги, кнопка
 * перехода в корзину. Действия выполняет media/com_dharmashop/js/mini-cart.js (data-атрибуты); без JavaScript панель не открывается,
 * а значок ведёт на страницу корзины. Данные готовит вид (`$this->lines`, `$this->totals`), SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$cartUrl = Route::_('index.php?option=com_dharmashop&view=cart', false);
?>
<div class="dharmaMiniCart" data-dharmashop-minicart-content>
<?php if (!$this->lines) : ?>
    <p class="text-body-secondary mb-3"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="offcanvas"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></button>
<?php else : ?>
    <ul class="list-unstyled mb-3">
        <?php foreach ($this->lines as $line) : ?>
            <?php $step = (float) ($line->qty_step ?: 1); $min = (float) ($line->qty_min ?: $step); ?>
            <li class="d-flex gap-3 py-3 border-bottom dharmaMiniCartLine" data-line="<?php echo (int) $line->id; ?>">
                <?php if ($line->image !== '') : ?>
                    <a class="flex-shrink-0" href="<?php echo $this->escape($line->url); ?>" tabindex="-1" aria-hidden="true"><img class="rounded object-fit-cover" src="<?php echo $this->escape($line->image); ?>" width="56" height="56" alt="" loading="lazy" decoding="async"></a>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <a class="fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $this->escape($line->url); ?>"><?php echo $this->escape($line->title); ?></a>
                    <?php if ($line->variant_title) : ?><div class="small text-body-secondary"><?php echo $this->escape($line->variant_title); ?></div><?php endif; ?>
                    <?php if ($line->error !== '') : ?><div class="small text-danger"><?php echo $this->escape(Text::_($line->error)); ?></div><?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                        <div class="input-group input-group-sm flex-nowrap w-auto">
                            <button class="btn btn-outline-secondary" type="button" data-dharmashop-minicart-step="down" aria-label="−" title="−">−</button>
                            <input type="text" inputmode="decimal" size="3" class="form-control text-center px-1" value="<?php echo $this->escape(rtrim(rtrim(number_format($line->qty, 3, '.', ''), '0'), '.')); ?>" data-dharmashop-minicart-qty="<?php echo (int) $line->id; ?>" data-min="<?php echo $this->escape((string) $min); ?>" data-step="<?php echo $this->escape((string) $step); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                            <button class="btn btn-outline-secondary" type="button" data-dharmashop-minicart-step="up" aria-label="+" title="+">+</button>
                        </div>
                        <span class="fw-semibold text-nowrap"><?php echo $line->error === '' ? $this->escape($this->money->format((string) $line->total)) : '—'; ?></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-dharmashop-minicart-remove="<?php echo (int) $line->id; ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>">&times;</button>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="d-flex justify-content-between h5 mb-3"><span><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL'); ?></span><span class="fw-bold"><?php echo $this->escape($this->money->format((string) $this->totals['total'])); ?></span></div>
    <div class="d-grid gap-2">
        <a class="btn btn-outline-primary" href="<?php echo $this->escape($cartUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_GO'); ?></a>
        <button type="button" class="btn btn-link" data-bs-dismiss="offcanvas"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></button>
    </div>
<?php endif; ?>
</div>
