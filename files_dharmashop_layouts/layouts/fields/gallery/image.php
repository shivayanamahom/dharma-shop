<?php
/** Shared image card; override: html/layouts/components/dharmashop/fields/gallery/image.php */
\defined('_JEXEC') or die;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$image = $displayData['image'];
$alt = trim((string) $image->text) ?: (string) $field->title;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fit = $gallery['fit'] ?? 'cover';
$natural = $fit === 'original';
$frameClass = $natural ? 'd-flex justify-content-center align-items-start' : 'd-block ratio ratio-' . ($gallery['ratio'] ?? '4x3') . ' bg-body-tertiary';
$imageClass = $natural ? 'img-fluid d-block' : 'w-100 h-100 object-fit-' . ($fit === 'contain' ? 'contain' : 'cover');
?>
<div class="card overflow-hidden<?php echo $natural ? '' : ' h-100'; ?>">
    <?php if ($gallery['lightbox']) : ?>
        <a class="<?php echo $escape($frameClass); ?>" href="<?php echo $escape($image->url); ?>" data-fancybox="<?php echo $gallery['id']; ?>" data-caption="<?php echo $escape($image->text); ?>" aria-label="<?php echo $escape($alt); ?>">
    <?php else : ?>
        <div class="<?php echo $escape($frameClass); ?>">
    <?php endif; ?>
        <img class="<?php echo $escape($imageClass); ?>" src="<?php echo $escape($image->image); ?>"<?php echo (string) ($image->image_attrs ?? ''); ?> alt="<?php echo $escape($alt); ?>" loading="lazy" decoding="async">
    <?php echo $gallery['lightbox'] ? '</a>' : '</div>'; ?>
    <?php if ($gallery['caption'] && trim((string) $image->text) !== '') : ?>
        <div class="card-body py-2"><p class="card-text small mb-0"><?php echo $escape($image->text); ?></p></div>
    <?php endif; ?>
</div>
