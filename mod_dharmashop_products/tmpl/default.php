<?php
\defined('_JEXEC') or die;
if (!$items) { return; }
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="dharmaProductsModule" data-dharma-products-module="<?php echo (int) $moduleId; ?>">
    <?php if ($slider) : ?>
        <?php require $sliderTemplate; ?>
    <?php elseif ($display === 'list') : ?>
        <div class="d-grid gap-3">
            <?php foreach ($items as $item) : ?><?php echo $renderCard($item); ?><?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="<?php echo $escape($gridClass); ?>">
            <?php foreach ($items as $item) : ?><div class="col"><?php echo $renderCard($item); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
