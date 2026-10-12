<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Layout\FileLayout;
$item = $displayData['item'];
$group = $displayData['group'];
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$image = trim((string) ($group->cover ?? '')) ?: trim((string) ($item->cover ?? ''));
?>
<article class="card h-100 overflow-hidden dharmaGroupCard<?php echo ($displayData['mode'] ?? '') === 'list' ? ' dharmaCardList' : ''; ?>">
    <div class="position-relative">
    <a class="d-block text-decoration-none" href="<?php echo $escape($displayData['url']); ?>">
        <?php if ($image !== '') : ?><div class="<?php echo ($displayData['mode'] ?? '') === 'list' ? 'ratio ratio-4x3 bg-body-tertiary' : 'ratio ratio-1x1 bg-body-tertiary'; ?>"><img class="w-100 h-100 object-fit-contain" src="<?php echo $escape(ImageUrl::resolve($image, (string) $displayData['imageContext'])); ?>" alt="<?php echo $escape($group->cover_alt ?: $group->title); ?>" loading="lazy" decoding="async"></div>
        <?php else : ?><div class="ratio ratio-1x1 bg-body-tertiary"><?php echo (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render([]); ?></div><?php endif; ?>
        <div class="card-body"><h3 class="h5 card-title text-body mb-0"><?php echo $escape($group->title); ?></h3></div>
    </a>
    <?php echo (new FileLayout('components.dharmashop.product-card-parts.favorite', null, ['component' => 'none', 'client' => 0]))->render($displayData); ?>
    </div>
    <div class="card-footer bg-transparent"><div class="fw-bold"><?php echo $escape($displayData['priceRange']); ?></div><span class="small text-body-secondary"><?php echo (int) ($group->product_count ?? 0); ?></span></div>
</article>
