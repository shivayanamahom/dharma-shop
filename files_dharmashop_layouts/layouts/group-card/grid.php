<?php
/** Карточка группы в общей сетке с обычными товарами. */
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$part = static fn(string $name): string => (new FileLayout('components.dharmashop.group-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
?>
<article class="card h-100 dharmaGroupCard" <?php echo $part('attributes'); ?>>
    <?php echo $part('media'); ?>
    <div class="card-body">
        <h3 class="h5 card-title mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($displayData['title']); ?></a></h3>
        <?php if ($displayData['showIntro']) : ?><?php echo (new FileLayout('components.dharmashop.product-card-parts.intro', null, ['component' => 'none', 'client' => 0]))->render(['intro' => $displayData['intro'] ?? '']); ?><?php endif; ?>
        <?php echo $part('details'); ?>
    </div>
    <div class="card-footer bg-transparent dharmaGroupFooter"><?php echo $part('actions'); ?></div>
</article>
