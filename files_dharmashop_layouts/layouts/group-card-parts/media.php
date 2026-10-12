<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (!empty($displayData['ajax']) && $displayData['mode'] !== 'table') {
    echo (new FileLayout('components.dharmashop.product-card-parts.media', null, ['component' => 'none', 'client' => 0]))->render($displayData['productData']);
    return;
}
?>
<?php if ($displayData['mode'] === 'table') : ?>
<a class="flex-shrink-0" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
    <?php if ($displayData['image'] !== '') : ?><img class="rounded object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($displayData['image'], $displayData['imageContext'])); ?>" width="56" height="56" alt="" loading="lazy" decoding="async"><?php else : ?><?php echo (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render(['size' => 56]); ?><?php endif; ?>
</a>
<?php else : ?>
<a class="d-block ratio ratio-1x1 overflow-hidden rounded-top dharmaGroupImage" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
    <?php if ($displayData['image'] !== '') : ?>
        <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($displayData['image'], $displayData['imageContext'])); ?>" alt="" loading="lazy" decoding="async">
    <?php else : ?><?php echo (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render([]); ?><?php endif; ?>
</a>
<?php endif; ?>
