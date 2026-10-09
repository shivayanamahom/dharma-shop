<?php
/** Shared image card; override: html/layouts/components/dharmashop/fields/gallery/image.php */
\defined('_JEXEC') or die;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$image = $displayData['image'];
$alt = trim((string) $image->text) ?: (string) $field->title;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="card h-100">
    <?php if ($gallery['lightbox']) : ?>
        <a class="d-block ratio ratio-4x3" href="<?php echo $escape($image->url); ?>" data-fancybox="<?php echo $gallery['id']; ?>" data-caption="<?php echo $escape($image->text); ?>" aria-label="<?php echo $escape($alt); ?>">
    <?php else : ?>
        <div class="ratio ratio-4x3">
    <?php endif; ?>
        <img class="w-100 h-100 object-fit-contain" src="<?php echo $escape($image->image); ?>" alt="<?php echo $escape($alt); ?>" loading="lazy" decoding="async">
    <?php echo $gallery['lightbox'] ? '</a>' : '</div>'; ?>
    <?php if ($gallery['caption'] && trim((string) $image->text) !== '') : ?>
        <div class="card-body py-2"><p class="card-text small mb-0"><?php echo $escape($image->text); ?></p></div>
    <?php endif; ?>
</div>
