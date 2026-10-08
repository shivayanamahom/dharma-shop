<?php
/**
 * Уведомление об успешном добавлении. Переопределение:
 * templates/<шаблон>/html/layouts/components/dharmashop/cart/toast.php
 *
 * displayData: product {product_id,title,sku,variant_title,cover,qty,unit}, cartUrl, seconds (0 — вручную).
 * Сохраните data-dharmashop-cart-toast-host / data-dharmashop-cart-toast и data-bs-dismiss="toast".
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$product = (array) ($displayData['product'] ?? []);
$url = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) ($product['product_id'] ?? 0), false);
$image = ImageUrl::resolve((string) ($product['cover'] ?? ''), 'cart.toast');
$qty = rtrim(rtrim(number_format((float) ($product['qty'] ?? 0), 3, '.', ''), '0'), '.');
$quantity = trim($qty . ' ' . (string) ($product['unit'] ?? ''));
$seconds = max(0, min(300, (int) ($displayData['seconds'] ?? 5)));
?>
<?php // Нативный popover помещает тост выше мини-корзины, без собственных z-index и CSS. ?>
<div class="toast-container position-fixed top-0 end-0 m-0 ms-auto p-3 border-0 bg-transparent mw-100 dharmaCartToastContainer" popover="manual" data-dharmashop-cart-toast-host>
    <div class="toast bg-body text-body shadow border-0 mw-100 dharmaCartToast" role="status" aria-live="polite" aria-atomic="true" data-dharmashop-cart-toast data-bs-autohide="<?php echo $seconds > 0 ? 'true' : 'false'; ?>" data-bs-delay="<?php echo $seconds * 1000; ?>">
        <div class="toast-header bg-body text-body gap-2 py-3">
            <span class="icon-check text-success" aria-hidden="true"></span>
            <strong class="me-auto"><?php echo Text::_('COM_DHARMASHOP_CART_ADDED'); ?></strong>
            <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="toast" aria-label="<?php echo $escape(Text::_('JCLOSE')); ?>"></button>
        </div>
        <div class="toast-body p-3">
            <div class="d-flex align-items-start gap-3">
                <?php if ($image !== '') : ?>
                    <a class="flex-shrink-0" href="<?php echo $escape($url); ?>" tabindex="-1" aria-hidden="true">
                        <img class="rounded border object-fit-contain bg-white dharmaCartToastImage" src="<?php echo $escape($image); ?>" width="64" height="64" alt="" decoding="async">
                    </a>
                <?php endif; ?>
                <div class="flex-grow-1 text-break">
                    <a class="fw-semibold text-body-emphasis text-decoration-none dharmaCartToastTitle" href="<?php echo $escape($url); ?>"><?php echo $escape($product['title'] ?? ''); ?></a>
                    <?php if (!empty($product['variant_title'])) : ?>
                        <div class="small text-body-secondary mt-1"><?php echo $escape($product['variant_title']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($product['sku'])) : ?>
                        <div class="small text-body-secondary mt-1 dharmaCartToastSku"><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $escape($product['sku']); ?></div>
                    <?php endif; ?>
                    <div class="mt-2 dharmaCartToastQty"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_CART_TOAST_QTY', $quantity)); ?></div>
                </div>
            </div>
            <a class="d-inline-block mt-3" href="<?php echo $escape($displayData['cartUrl'] ?? ''); ?>"><?php echo Text::_('COM_DHARMASHOP_CART_GO'); ?></a>
        </div>
        <?php if ($seconds > 0) : ?>
            <div class="overflow-hidden rounded-bottom" aria-hidden="true">
                <div class="w-100 border-top border-2 border-primary dharmaCartToastProgress" data-dharmashop-cart-toast-progress></div>
            </div>
        <?php endif; ?>
    </div>
</div>
