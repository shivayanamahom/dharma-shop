<?php
/**
 * Строка товара в таблице (Bootstrap): миниатюра, название с категорией и бейджами, колонки характеристик (набор колонок готовит вид
 * по товарам страницы), цена с действиями. Общие части — product-card-parts. Заголовок таблицы выводит шаблон списка.
 * Адаптивность: колонки характеристик — от lg (ниже они выводятся строками под названием), цена и действия — одна колонка
 * (на узких экранах без виджета количества и подписи кнопки), название забирает свободную ширину.
 */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$images = (array) ($displayData['images'] ?? []);
$columns = (array) ($displayData['fieldColumns'] ?? []);
$fields = (array) ($displayData['fields'] ?? $item->fields ?? []);
$part = static fn (string $name, array $extra = []): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($extra + $displayData);
$valueLayout = new FileLayout('components.dharmashop.fields.value', null, ['component' => 'none', 'client' => 0]);
?>
<tr class="dharmaCard dharmaCardRow"<?php if (!empty($displayData['favoritesPage'])) : ?> data-favorite-row="<?php echo (int) $item->id; ?>"<?php endif; ?>>
    <td class="w-100 dharmaCardTitle">
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
        <?php // Ниже lg колонок характеристик нет: значения — строками под названием. ?>
        <?php foreach ($columns as $alias => $title) : ?>
            <?php $field = $fields[$alias] ?? null; ?>
            <?php if ($field && !empty($field->show) && !empty($field->values)) : ?>
                <div class="d-lg-none small dharmaField" data-field="<?php echo $escape($alias); ?>"><span class="<?php echo empty($field->show_title ?? true) ? 'visually-hidden' : 'text-body-secondary'; ?>"><?php echo $escape($title); ?>:</span> <?php echo $valueLayout->render(['field' => $field]); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
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
    <?php foreach ($columns as $alias => $title) : ?>
        <td class="d-none d-lg-table-cell dharmaField" data-field="<?php echo $escape($alias); ?>">
            <?php $field = $fields[$alias] ?? null; ?>
            <?php if ($field && !empty($field->show) && !empty($field->values)) : ?><?php echo $valueLayout->render(['field' => $field]); ?><?php else : ?><span class="text-body-secondary">—</span><?php endif; ?>
        </td>
    <?php endforeach; ?>
    <td class="text-end text-nowrap dharmaCardPriceCell">
        <?php echo $part('price'); ?>
        <div class="mt-2"><?php echo $part('actions', ['compactActions' => true]); ?></div>
    </td>
</tr>
