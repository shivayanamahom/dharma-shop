<?php
/** Минимальная карточка товара в сетке. Данные и цены готовятся видом, SQL здесь нет. */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$image = MediaUrl::absolute((string) ($item->cover ?? ''));
$intro = trim(strip_tags((string) ($item->introtext ?? '')));
$price = (string) ($displayData['priceRange'] ?? '');
?>
<article class="card h-100">
    <?php if ($image !== '') : ?>
        <div class="ratio ratio-4x3 overflow-hidden rounded-top">
            <img class="w-100 h-100 object-fit-contain p-3" src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item->cover_alt ?: $item->title); ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>
    <div class="card-body d-flex flex-column">
        <h3 class="h5 card-title mb-0"><?php echo $escape($item->title); ?></h3>
        <?php if (!empty($displayData['showIntro']) && $intro !== '') : ?>
            <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <p class="card-text fw-semibold mt-auto pt-3 mb-0"><?php echo $escape($price !== '' ? $price : Text::_('COM_DHARMASHOP_PRODUCT_PRICE_REQUEST')); ?></p>
    </div>
</article>
