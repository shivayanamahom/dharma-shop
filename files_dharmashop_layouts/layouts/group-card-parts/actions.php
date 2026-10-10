<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if (!empty($displayData['ajax'])) : ?>
    <?php echo (new FileLayout('components.dharmashop.product-card-parts.price', null, ['component' => 'none', 'client' => 0]))->render($displayData['productData']); ?>
    <?php echo (new FileLayout('components.dharmashop.product-card-parts.actions', null, ['component' => 'none', 'client' => 0]))->render($displayData['productData']); ?>
    <a class="small d-inline-block mt-2" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_CARD_OPEN')); ?></a>
<?php else : ?>
    <div class="dharmaGroupPrice fw-bold"><?php echo $escape($displayData['priceRange']); ?></div>
    <a class="btn btn-outline-primary mt-2 dharmaGroupChoose" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape(Text::plural('COM_DHARMASHOP_GROUP_CARD_VARIANTS', (int) ($displayData['productCount'] ?? 0))); ?></a>
<?php endif; ?>
