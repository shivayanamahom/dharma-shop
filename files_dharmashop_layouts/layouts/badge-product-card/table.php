<?php
/** Копия стандартной карточки для страницы бейджа (используется, когда выключен переключатель «Карточки товаров как в категориях»), правится отдельно.
 *
 * Строка таблицы: товар и цена с действиями. Характеристики отдельно не выводятся.
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$images = (array) ($displayData['images'] ?? []);
$part = static fn (string $name, array $extra = []): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($extra + $displayData);
?>
<tr class="dharmaCard dharmaCardRow">
    <td class="dharmaCardTitle">
        <?php // Миниатюра внутри ячейки названия, а не отдельной колонкой: у img max-width 100% в шаблоне, колонка схлопывается. ?>
        <div class="d-flex gap-3">
        <div class="position-relative flex-shrink-0">
        <a class="flex-shrink-0 dharmaCardThumb" href="<?php echo $escape($productUrl); ?>" tabindex="-1" aria-hidden="true">
            <?php if ($images) : ?>
                <img class="rounded object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($images[0]->url, (string) ($displayData['imageContext'] ?? 'product.category.table'))); ?>" width="56" height="56" alt="" loading="lazy" decoding="async">
            <?php else : ?>
                <?php echo (new FileLayout('components.dharmashop.media.placeholder', null, ['component' => 'none', 'client' => 0]))->render(['size' => 56]); ?>
            <?php endif; ?>
        </a>
        <?php echo $part('favorite', ['favoriteButtonClass' => 'btn btn-light rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center p-1 lh-1', 'favoriteFormClass' => 'position-absolute top-0 end-0 m-1 z-2']); ?>
        </div>
        <div>
        <?php if ($categoryTitle !== '') : ?>
            <div class="small text-body-secondary dharmaCardCategory"><a class="link-secondary text-decoration-none" href="<?php echo $escape($displayData['categoryUrl'] ?? '#'); ?>"><?php echo $escape($categoryTitle); ?></a></div>
        <?php endif; ?>
        <a class="fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $escape($productUrl); ?>"><?php echo $escape($item->title); ?></a>
        <?php if (!empty($displayData['badges'])) : ?>
            <div class="d-flex flex-wrap gap-1 mt-1 dharmaCardBadges">
                <?php foreach ($displayData['badges'] as $badge) : ?>
                    <a class="badge text-bg-<?php echo $escape($badge->color); ?> text-decoration-none" href="<?php echo $escape($badge->url ?? '#'); ?>"><?php echo $escape($badge->title); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
        </div>
    </td>
    <td class="text-end text-nowrap dharmaCardPriceCell">
        <?php echo $part('price'); ?>
        <div class="mt-2"><?php echo $part('actions', ['compactActions' => true]); ?></div>
    </td>
</tr>
