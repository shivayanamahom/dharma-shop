<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$product = $displayData['product'];
$hasImage = !empty($product['image']);
?>
<div data-dharmashop-product-field data-picker-product="<?php echo $escape(json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
    <div class="row g-3">
      <div class="col-12" data-group-member-main>
       <div class="d-flex align-items-start gap-3">
        <div class="flex-shrink-0" data-picker-preview<?php echo $product ? '' : ' hidden'; ?>>
            <img data-picker-image class="rounded object-fit-cover" width="64" height="64" alt="" loading="lazy" decoding="async"<?php echo $hasImage ? ' src="' . $escape($product['image']) . '"' : ' hidden'; ?>>
            <span data-picker-placeholder class="icon-image text-body-secondary p-3" aria-hidden="true"<?php echo $hasImage ? ' hidden' : ''; ?>></span>
        </div>
        <div class="flex-grow-1 w-100">
            <?php echo $displayData['input']; ?>
            <div data-picker-summary<?php echo $product ? '' : ' hidden'; ?> aria-live="polite">
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span data-picker-price class="border rounded bg-body-tertiary px-3 py-1 fs-4 fw-bold"><?php echo $escape($product['price'] ?? ''); ?></span>
                    <del data-picker-regular class="small text-body-secondary"<?php echo empty($product['regular']) ? ' hidden' : ''; ?>><?php echo $escape($product['regular'] ?? ''); ?></del>
                    <span data-picker-discount class="badge bg-danger"<?php echo empty($product['discount']) ? ' hidden' : ''; ?>><?php echo $escape($product['discount'] ?? ''); ?></span>
                    <span class="border rounded bg-body-tertiary px-3 py-2">
                        <span class="small text-body-secondary"><?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_MEMBER_STOCK_LABEL')); ?></span>
                        <span data-picker-stock class="fw-semibold ms-1"><?php echo $escape($product['stock'] ?? ''); ?></span>
                    </span>
                    <span class="border rounded bg-body-tertiary px-3 py-2">
                        <span class="small text-body-secondary"><?php echo $escape(Text::_('JSTATUS')); ?></span>
                        <span data-picker-state class="fw-semibold ms-1"><?php echo $escape($product['state'] ?? ''); ?></span>
                    </span>
                    <a data-picker-edit class="btn btn-outline-primary" target="_blank" rel="noopener"<?php echo !empty($product['editUrl']) ? ' href="' . $escape($product['editUrl']) . '"' : ' hidden'; ?>>
                        <?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_MEMBER_EDIT_PRODUCT')); ?>
                    </a>
                </div>
            </div>
        </div>
       </div>
      </div>
      <div class="col-12 col-lg-4" data-group-member-panel hidden>
        <div class="rounded bg-body-tertiary p-2 h-100">
          <div class="small text-body-secondary mb-2"><?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_MEMBER_CHOICES')); ?></div>
          <dl class="d-flex flex-column gap-2 mb-0" data-group-member-choices data-empty="<?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_MEMBER_VALUE_EMPTY')); ?>" aria-live="polite"></dl>
        </div>
      </div>
    </div>
</div>
