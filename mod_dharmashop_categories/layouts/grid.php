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
    <?php if ($image !== '') : ?>
        <a class="ratio ratio-4x3 overflow-hidden rounded-top" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
            <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item->params->get('image_alt') ?: $item->title); ?>" loading="lazy" decoding="async">
        </a>
    <?php endif; ?>
    <div class="card-body">
        <h3 class="h5 card-title"><a class="text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($item->title); ?></a></h3>
        <?php if ($displayData['showIntro'] && $intro !== '') : ?>
            <p class="card-text text-body-secondary mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <?php echo $displayData['renderStats']($displayData); ?>
    </div>
</article>
