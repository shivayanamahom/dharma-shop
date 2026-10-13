<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/video/item.php */
\defined('_JEXEC') or die;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$video = $displayData['video'];
$index = (int) ($displayData['index'] ?? 0);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = trim((string) ($video->text ?? '')) ?: (string) $field->title;
$caption = $gallery['caption'] ? $title : '';
$id = $gallery['id'] . '-player-' . $index;
$local = ($video->player_type ?? '') === 'video';
$mediaType = (string) ($video->mime ?? 'video/mp4');
$play = '<span class="d-flex align-items-center justify-content-center" aria-hidden="true">'
    . '<span class="d-inline-flex rounded-circle bg-dark bg-opacity-75 text-white p-3">'
    . '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5v14l11-7z" /></svg>'
    . '</span></span>';
?>
<?php if ($gallery['lightbox']) : ?>
    <a class="card h-100 overflow-hidden text-decoration-none" href="<?php echo $escape($video->url); ?>" data-fancybox="<?php echo $escape($gallery['id']); ?>" data-type="<?php echo $local ? 'html5video' : 'iframe'; ?>" data-width="1280" data-height="720" data-caption="<?php echo $escape($caption); ?>"<?php echo $local ? ' data-html5video-format="' . $escape($mediaType) . '"' : ''; ?> aria-label="<?php echo $escape($title); ?>">
        <span class="ratio ratio-16x9 bg-body-tertiary d-flex align-items-center justify-content-center overflow-hidden">
            <?php if (!empty($video->image)) : ?><img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($video->image); ?>"<?php echo (string) ($video->image_attrs ?? ''); ?> alt="<?php echo $escape($title); ?>" loading="lazy" decoding="async"><?php endif; ?>
            <?php echo $play; ?>
        </span>
        <?php if ($gallery['caption']) : ?><span class="card-body py-2"><span class="card-text small"><?php echo $escape($title); ?></span></span><?php endif; ?>
    </a>
<?php else : ?>
    <details class="card h-100 overflow-hidden">
        <summary class="list-unstyled">
            <span class="ratio ratio-16x9 bg-body-tertiary d-flex align-items-center justify-content-center overflow-hidden">
                <?php if (!empty($video->image)) : ?><img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($video->image); ?>"<?php echo (string) ($video->image_attrs ?? ''); ?> alt="<?php echo $escape($title); ?>" loading="lazy" decoding="async"><?php endif; ?>
                <?php echo $play; ?>
            </span>
            <?php if ($gallery['caption']) : ?><span class="card-body py-2 d-block"><span class="card-text small"><?php echo $escape($title); ?></span></span><?php endif; ?>
        </summary>
        <div class="ratio ratio-16x9">
            <?php if ($local) : ?><video controls preload="none"<?php echo !empty($video->poster) ? ' poster="' . $escape($video->poster) . '"' : ''; ?>><source src="<?php echo $escape($video->url); ?>" type="<?php echo $escape($mediaType); ?>"></video>
            <?php else : ?><iframe src="<?php echo $escape($video->embed); ?>" title="<?php echo $escape($title); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe><?php endif; ?>
        </div>
    </details>
<?php endif; ?>
