<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
$item = $displayData['item'];
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = (string) ($displayData['url'] ?? $displayData['productUrl'] ?? Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false));
$image = trim((string) ($item->cover ?? ''));
$list = ($displayData['mode'] ?? '') === 'list';
$placeholder = (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render([]);
?>
<article class="card h-100 overflow-hidden dharmaCard<?php echo $list ? ' dharmaCardList' : ''; ?>">
    <div class="<?php echo $list ? 'row g-0 h-100' : ''; ?>">
        <div class="position-relative<?php echo $list ? ' col-12 col-md-4 col-xl-3' : ''; ?>">
        <a class="d-block text-decoration-none" href="<?php echo $escape($url); ?>">
            <?php if ($image !== '') : ?><div class="ratio <?php echo $list ? 'ratio-4x3' : 'ratio-1x1'; ?> bg-body-tertiary"><img class="w-100 h-100 object-fit-contain" src="<?php echo $escape(ImageUrl::resolve($image, (string) $displayData['imageContext'])); ?>"<?php echo ImageUrl::attrs($image, (string) $displayData['imageContext']); ?> alt="<?php echo $escape($item->cover_alt ?: $item->title); ?>" loading="lazy" decoding="async"></div>
            <?php else : ?><div class="ratio <?php echo $list ? 'ratio-4x3' : 'ratio-1x1'; ?> bg-body-tertiary"><?php echo $placeholder; ?></div><?php endif; ?>
        </a>
        <?php echo (new FileLayout('components.dharmashop.product-card-parts.favorite', null, ['component' => 'none', 'client' => 0]))->render($displayData); ?>
        </div>
        <div class="<?php echo $list ? 'col' : ''; ?>">
            <a class="d-block text-decoration-none" href="<?php echo $escape($url); ?>"><div class="card-body"><h3 class="h5 card-title text-body mb-0"><?php echo $escape($item->title); ?></h3>
                <?php if ($list && trim(strip_tags((string) ($item->introtext ?? ''))) !== '') : ?><p class="card-text text-body-secondary mt-2 mb-0"><?php echo $escape(trim(strip_tags((string) $item->introtext))); ?></p><?php endif; ?>
            </div></a>
            <div class="card-footer bg-transparent"><div class="fw-bold"><?php echo $escape($displayData['priceRange']); ?></div></div>
        </div>
    </div>
</article>
