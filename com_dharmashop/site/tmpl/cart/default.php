<?php
/**
 * Страница корзины: строки с количеством и ценой, итоги. Работает без JavaScript (обычные формы: «Пересчитать», «Удалить»,
 * «Очистить»); данные готовит вид (`$this->lines`, `$this->totals`), SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$remove = Route::_('index.php?option=com_dharmashop&task=cart.remove', false);
$clear = Route::_('index.php?option=com_dharmashop&task=cart.clear', false);
$hasErrors = (bool) array_filter($this->lines, static fn (object $line): bool => $line->error !== '');
?>
<div id="dharma_shop" class="dharmaCart">
<section class="py-4">
    <h1 class="mb-4"><?php echo Text::_('COM_DHARMASHOP_CART_TITLE'); ?></h1>
    <?php if (!$this->lines) : ?>
        <p class="text-body-secondary dharmaCartEmpty"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p>
        <a class="btn btn-outline-primary" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
    <?php else : ?>
        <form action="<?php echo $this->escape($this->actionUrl); ?>" method="post" class="dharmaCartForm">
            <div class="table-responsive">
                <table class="table align-middle dharmaCartTable">
                    <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_CART_TITLE'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CART_COL_PRODUCT'); ?></th>
                            <th scope="col" class="text-end d-none d-md-table-cell"><?php echo Text::_('COM_DHARMASHOP_CART_COL_PRICE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_DHARMASHOP_CART_COL_QTY'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_CART_COL_TOTAL'); ?></th>
                            <th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_CART_REMOVE'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->lines as $line) : ?>
                            <tr class="dharmaCartLine" data-line="<?php echo (int) $line->id; ?>">
                                <td>
                                    <div class="d-flex gap-3">
                                        <?php if ($line->image !== '') : ?>
                                            <a class="flex-shrink-0" href="<?php echo $this->escape($line->url); ?>" tabindex="-1" aria-hidden="true"><img class="rounded object-fit-cover" src="<?php echo $this->escape($line->image); ?>" width="64" height="64" alt="" loading="lazy" decoding="async"></a>
                                        <?php endif; ?>
                                        <div>
                                            <a class="fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $this->escape($line->url); ?>"><?php echo $this->escape($line->title); ?></a>
                                            <?php if ($line->variant_title) : ?><div class="small text-body-secondary"><?php echo $this->escape($line->variant_title); ?></div><?php endif; ?>
                                            <?php if ($line->sku) : ?><div class="small text-body-secondary"><?php echo $this->escape($line->sku); ?></div><?php endif; ?>
                                            <?php if ($line->error !== '') : ?><div class="small text-danger"><?php echo $this->escape(Text::_($line->error)); ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap d-none d-md-table-cell"><?php echo $line->unit_price !== null ? $this->escape($this->money->price((string) $line->unit_price)) : '—'; ?></td>
                                <td>
                                    <div class="input-group input-group-sm flex-nowrap w-auto">
                                        <input type="text" inputmode="decimal" size="4" class="form-control text-center" name="qty[<?php echo (int) $line->id; ?>]" value="<?php echo $this->escape(rtrim(rtrim(number_format($line->qty, 3, '.', ''), '0'), '.')); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_QUANTITY')); ?>">
                                        <?php if ($line->unit) : ?><span class="input-group-text"><?php echo $this->escape($line->unit); ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap fw-semibold"><?php echo $line->error === '' ? $this->escape($this->money->price((string) $line->total)) : '—'; ?></td>
                                <td class="text-end">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" name="item_id" value="<?php echo (int) $line->id; ?>" formaction="<?php echo $this->escape($remove); ?>" title="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CART_REMOVE')); ?>">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline-primary"><?php echo Text::_('COM_DHARMASHOP_CART_UPDATE'); ?></button>
                    <button type="submit" class="btn btn-outline-secondary" formaction="<?php echo $this->escape($clear); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CLEAR'); ?></button>
                    <a class="btn btn-link" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_CONTINUE'); ?></a>
                </div>
                <dl class="mb-0 dharmaCartTotals">
                    <div class="d-flex justify-content-between gap-5"><dt class="fw-normal"><?php echo Text::_('COM_DHARMASHOP_CART_SUBTOTAL'); ?></dt><dd class="mb-0"><?php echo $this->escape($this->totalText((float) $this->totals['subtotal'])); ?></dd></div>
                    <?php foreach ($this->totals['adjustments'] as $adjustment) : ?>
                        <div class="d-flex justify-content-between gap-5"><dt class="fw-normal"><?php echo $this->escape($adjustment['title']); ?></dt><dd class="mb-0"><?php echo $this->escape($this->money->format((string) $adjustment['amount'])); ?></dd></div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between gap-5 h4 mt-2"><dt><?php echo Text::_('COM_DHARMASHOP_CART_TOTAL'); ?></dt><dd class="mb-0 fw-bold"><?php echo $this->escape($this->totalText((float) $this->totals['total'])); ?></dd></div>
                    <?php if ($hasErrors) : ?><div class="small text-danger"><?php echo Text::_('COM_DHARMASHOP_CART_HAS_ERRORS'); ?></div><?php endif; ?>
                </dl>
            </div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</section>
</div>
