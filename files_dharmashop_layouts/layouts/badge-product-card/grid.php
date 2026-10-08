<?php
/** Копия стандартной карточки для страницы бейджа (используется, когда выключен переключатель «Карточки товаров как в категориях»), правится отдельно.
 *
 * Карточка товара в сетке (Bootstrap card): область изображения с бейджами, категория над названием, характеристики,
 * футер с ценой слева и количеством с кнопкой «В корзину» справа. Общие части — product-card-parts. Данные готовит вид, SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$intro = trim(strip_tags((string) ($displayData['intro'] ?? $item->introtext ?? '')));
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$part = static fn (string $name): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
?>
<article class="card h-100 dharmaCard">
    <?php echo $part('media'); ?>
    <div class="card-body">
        <?php if ($categoryTitle !== '') : ?>
            <div class="small text-body-secondary mb-1 dharmaCardCategory"><a class="link-secondary text-decoration-none" href="<?php echo $escape($displayData['categoryUrl'] ?? '#'); ?>"><?php echo $escape($categoryTitle); ?></a></div>
        <?php endif; ?>
        <h3 class="h5 card-title mb-0"><a class="link-body-emphasis text-decoration-none" href="<?php echo $escape($productUrl); ?>"><?php echo $escape($item->title); ?></a></h3>
        <?php if (!empty($displayData['showIntro']) && $intro !== '') : ?>
            <p class="card-text text-body-secondary mt-3 mb-0"><?php echo $escape($intro); ?></p>
        <?php endif; ?>
        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $displayData['fields'] ?? $item->fields ?? [], 'context' => 'card']); ?>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-end gap-2 dharmaCardFooter">
        <?php echo $part('price'); ?>
        <?php // Справа: виджет количества и кнопка «В корзину»; в режиме каталога их нет. ?>
        <?php echo $part('actions'); ?>
    </div>
</article>
