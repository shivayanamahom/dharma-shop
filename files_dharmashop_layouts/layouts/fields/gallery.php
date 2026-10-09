<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/gallery.php */
\defined('_JEXEC') or die;

$field = $displayData['field'];
$settings = (array) ($field->gallery_settings ?? []);
$layout = ($settings['gallery_layout'] ?? 'grid') === 'list' ? 'list' : 'grid';
$columns = max(2, min(6, (int) ($settings['gallery_columns'] ?? 3)));
$showCaption = (int) ($settings['gallery_caption'] ?? 1) === 1;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<ul class="list-unstyled row row-cols-1<?php echo $layout === 'grid' ? ' row-cols-md-2 row-cols-xl-' . $columns : ''; ?> g-3 mb-0 dharmaFieldGallery">
    <?php foreach ($field->values as $image) : ?>
        <?php $alt = trim((string) $image->text) ?: (string) $field->title; ?>
        <li class="col dharmaFieldGallery__item">
            <?php if ($layout === 'grid') : ?>
                <div class="card h-100">
                    <a class="d-block ratio ratio-4x3" href="<?php echo $escape($image->url); ?>" aria-label="<?php echo $escape($alt); ?>">
                        <img class="w-100 h-100 object-fit-contain" src="<?php echo $escape($image->image); ?>" alt="<?php echo $escape($alt); ?>" loading="lazy" decoding="async">
                    </a>
                    <?php if ($showCaption && trim((string) $image->text) !== '') : ?><div class="card-body py-2"><p class="card-text small mb-0"><?php echo $escape($image->text); ?></p></div><?php endif; ?>
                </div>
            <?php else : ?>
                <a class="card text-decoration-none" href="<?php echo $escape($image->url); ?>">
                    <span class="card-body d-flex align-items-center gap-3">
                        <span class="ratio ratio-4x3 w-25 flex-shrink-0"><img class="w-100 h-100 object-fit-contain" src="<?php echo $escape($image->image); ?>" alt="<?php echo $escape($alt); ?>" loading="lazy" decoding="async"></span>
                        <?php if ($showCaption && trim((string) $image->text) !== '') : ?><span><?php echo $escape($image->text); ?></span><?php endif; ?>
                    </span>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
