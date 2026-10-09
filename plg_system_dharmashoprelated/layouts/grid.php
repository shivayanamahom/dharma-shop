<?php
/** Собственная карточка плагина; переопределение html/layouts/plugins/system/dharmashoprelated/grid.php. */
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$part = static fn (string $name): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none','client' => 0]))->render($displayData);
?>
<article class="card h-100 dharmaRelatedCard">
    <?php echo $part('media'); ?>
    <div class="card-body d-flex flex-column gap-3">
        <h3 class="h5 mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($displayData['productUrl']); ?>"><?php echo $escape($item->title); ?></a></h3>
        <?php if ($displayData['showIntro'] && trim(strip_tags($displayData['intro'])) !== '') : ?><p class="text-body-secondary mb-0"><?php echo $escape(strip_tags($displayData['intro'])); ?></p><?php endif; ?>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-end gap-2"><?php echo $part('price'); ?><?php echo $part('actions'); ?></div>
</article>
