<?php
/** Независимая карточка бренда (копия карточки категории, правится отдельно). Только Bootstrap; данные SEO страницы не изменяет. */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Language\Text;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$image = '';
if ($displayData['showImage'] ?? true) {
    $image = MediaUrl::absolute((string) $item->params->get('image', ''));
    $image = $image ?: MediaUrl::absolute((string) $item->params->get('icon', ''));
}
$intro = trim(strip_tags((string) $item->params->get('introtext', '')));
?>
<article class="card h-100">
    <?php if ($image !== '') : ?>
        <a class="ratio ratio-4x3 overflow-hidden rounded-top" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
            <img class="w-100 h-100 object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($image, 'brand.card')); ?>" alt="<?php echo $escape($item->params->get('image_alt') ?: $item->title); ?>" loading="lazy" decoding="async">
        </a>
    <?php endif; ?>
    <div class="card-body">
        <h2 class="h5 card-title mb-0"><a class="text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($item->title); ?></a></h2>
        <?php if ($displayData['showIntro'] && $intro !== '') : ?>
            <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <?php if (!empty($displayData['showCount'])) : ?>
            <p class="card-text small text-body-secondary mt-3 mb-0"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_CARD_PRODUCT_COUNT', $displayData['productCount'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($displayData['showPrices']) && $displayData['priceRange'] !== '') : ?>
            <p class="card-text small mt-2 mb-0"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_CARD_PRICE_RANGE', $displayData['priceRange'])); ?></p>
        <?php endif; ?>
    <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $displayData['fields'] ?? $item->fields ?? [], 'context' => 'card']); ?>
    </div>
</article>
