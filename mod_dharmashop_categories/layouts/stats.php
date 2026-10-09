<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($displayData['showCount']) : ?>
    <p class="small text-body-secondary mt-2 mb-0"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_CARD_PRODUCT_COUNT', $displayData['productCount'])); ?></p>
<?php endif; ?>
<?php if ($displayData['showPrices'] && $displayData['priceRange'] !== '') : ?>
    <p class="small mt-2 mb-0"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_CARD_PRICE_RANGE', $displayData['priceRange'])); ?></p>
<?php endif; ?>
