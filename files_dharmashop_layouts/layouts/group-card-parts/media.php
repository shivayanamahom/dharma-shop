<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<a class="d-block ratio ratio-1x1 overflow-hidden rounded-top dharmaGroupImage" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
    <?php if ($displayData['image'] !== '') : ?>
        <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($displayData['image'], $displayData['imageContext'])); ?>" alt="" loading="lazy" decoding="async">
    <?php else : ?><span class="w-100 h-100 bg-body-tertiary"></span><?php endif; ?>
</a>
