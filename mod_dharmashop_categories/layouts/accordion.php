<?php
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$source = $displayData['showImage'] ? (string) ($item->params->get('image') ?: $item->params->get('icon', '')) : '';
$image = ImageUrl::resolve($source, $displayData['imageContext']);
$intro = trim(strip_tags((string) $item->params->get('introtext', '')));
?>
<article class="card h-100">
<div class="card-body">
<div class="row g-3 align-items-center">
    <?php if ($image !== '') : ?>
        <div class="col-4 col-md-3">
            <a class="ratio ratio-4x3 overflow-hidden rounded" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
                <img class="w-100 h-100 object-fit-contain" src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item->params->get('image_alt') ?: $item->title); ?>" loading="lazy" decoding="async">
            </a>
        </div>
    <?php endif; ?>
    <div class="col">
        <h4 class="h6"><a class="text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($item->title); ?></a></h4>
        <?php if ($displayData['showIntro'] && $intro !== '') : ?>
            <p class="text-body-secondary"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <?php echo $displayData['renderStats']($displayData); ?>
    </div>
</div>
</div>
</article>
