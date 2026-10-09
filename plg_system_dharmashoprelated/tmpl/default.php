<?php
/** @var array $block @var object[] $items @var Closure $renderCard */
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$slider = $block['layout'] === 'slider'
    && Factory::getApplication()->getDocument()->getWebAssetManager()->getRegistry()->exists('preset', 'dharmashop.swiper');
$grid = $block['layout'] !== 'list';
$columns = 'row-cols-1 row-cols-sm-' . min(2, $block['columns']) . ' row-cols-lg-' . $block['columns'];
?>
<section class="dharmaRelatedProducts my-4" id="dharma-related-<?php echo $escape($block['alias']); ?>" aria-label="<?php echo $escape($block['title']); ?>">
    <?php if ($position !== 'tabs') : ?><h2 class="h4 mb-3"><?php echo $escape($block['title']); ?></h2><?php endif; ?>
    <?php if ($slider) : ?>
        <?php require PluginHelper::getLayoutPath('system', 'dharmashoprelated', 'slider'); ?>
    <?php else : ?>
    <div class="row g-3 <?php echo $grid ? $columns : 'row-cols-1'; ?>">
        <?php foreach ($items as $item) : ?><div class="col"><?php echo $renderCard($item); ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($stepper !== '') : ?><template data-dharmashop-stepper-template><?php echo $stepper; ?></template><?php endif; ?>
</section>
