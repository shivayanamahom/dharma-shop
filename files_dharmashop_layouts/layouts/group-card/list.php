<?php
/** Карточка группы в общем списке с обычными товарами. */
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$part = static fn(string $name): string => (new FileLayout('components.dharmashop.group-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
?>
<article class="card dharmaGroupCard dharmaGroupCardList" <?php echo $part('attributes'); ?>>
    <div class="row g-0">
        <div class="col-sm-3"><?php echo $part('media'); ?></div>
        <div class="col-sm-9"><div class="card-body h-100 d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h3 class="h5 card-title mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($displayData['title']); ?></a></h3>
                <?php if ($displayData['showIntro']) : ?><?php echo (new FileLayout('components.dharmashop.product-card-parts.intro', null, ['component' => 'none', 'client' => 0]))->render(['intro' => $displayData['intro'] ?? '']); ?><?php endif; ?>
                <?php echo $part('details'); ?>
            </div>
            <div class="flex-shrink-0 dharmaGroupFooter"><?php echo $part('actions'); ?></div>
        </div></div>
    </div>
</article>
