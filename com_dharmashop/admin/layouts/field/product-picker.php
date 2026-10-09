<?php
\defined('_JEXEC') or die;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$product = $displayData['product'];
$hasImage = !empty($product['image']);
?>
<div data-dharmashop-product-field data-picker-product="<?php echo $escape(json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
    <div class="d-flex align-items-start gap-3">
        <div class="flex-shrink-0" data-picker-preview<?php echo $product ? '' : ' hidden'; ?>>
            <img data-picker-image class="rounded object-fit-cover" width="64" height="64" alt="" loading="lazy" decoding="async"<?php echo $hasImage ? ' src="' . $escape($product['image']) . '"' : ' hidden'; ?>>
            <span data-picker-placeholder class="icon-image text-body-secondary p-3" aria-hidden="true"<?php echo $hasImage ? ' hidden' : ''; ?>></span>
        </div>
        <div class="flex-grow-1 w-100">
            <?php echo $displayData['input']; ?>
            <div data-picker-summary<?php echo $product ? '' : ' hidden'; ?> aria-live="polite">
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span data-picker-price class="fw-bold"><?php echo $escape($product['price'] ?? ''); ?></span>
                    <del data-picker-regular class="small text-body-secondary"<?php echo empty($product['regular']) ? ' hidden' : ''; ?>><?php echo $escape($product['regular'] ?? ''); ?></del>
                    <span data-picker-discount class="badge bg-danger"<?php echo empty($product['discount']) ? ' hidden' : ''; ?>><?php echo $escape($product['discount'] ?? ''); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
