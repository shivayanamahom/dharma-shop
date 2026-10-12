<?php
/**
 * Карточка товара в списке (Bootstrap card, горизонтальная): слева изображение с бейджами, справа категория, название, интро, характеристики
 * и строка с ценой и количеством с кнопкой «В корзину». Общие части — product-card-parts. Данные готовит вид, SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$intro = trim(strip_tags((string) ($displayData['intro'] ?? $item->introtext ?? '')));
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$part = static fn (string $name, array $extra = []): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($extra + $displayData);
?>
<article class="card h-100 overflow-hidden dharmaCard dharmaCardList"<?php if (!empty($displayData['favoritesPage'])) : ?> data-favorite-row="<?php echo (int) $item->id; ?>"<?php endif; ?>>
    <div class="row g-0 h-100">
        <div class="col-12 col-md-4 col-xl-3">
            <?php echo $part('media', ['mediaRounded' => 'start']); ?>
        </div>
        <div class="col">
            <div class="card-body d-flex flex-column h-100">
                <?php if ($categoryTitle !== '') : ?>
                    <div class="small text-body-secondary mb-1 dharmaCardCategory"><a class="link-secondary text-decoration-none" href="<?php echo $escape($displayData['categoryUrl'] ?? '#'); ?>"><?php echo $escape($categoryTitle); ?></a></div>
                <?php endif; ?>
                <h3 class="h5 card-title mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($productUrl); ?>"><?php echo $escape($item->title); ?></a></h3>
                <?php if (!empty($displayData['showIntro']) && $intro !== '') : ?>
                    <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
                <?php endif; ?>
                <?php echo (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $displayData['fields'] ?? $item->fields ?? [], 'context' => 'card']); ?>
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-auto pt-3 dharmaCardFooter">
                    <?php echo $part('price'); ?>
                    <?php echo $part('actions'); ?>
                </div>
            </div>
        </div>
    </div>
</article>
