<?php
/**
 * Строка товара в таблице (Bootstrap): миниатюра, название с категорией и бейджами, колонки характеристик (набор колонок готовит вид
 * по товарам страницы), цена, действия. Общие части — product-card-parts. Заголовок таблицы выводит шаблон списка.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productUrl = (string) ($displayData['productUrl'] ?? '#');
$categoryTitle = (string) ($displayData['categoryTitle'] ?? '');
$images = (array) ($displayData['images'] ?? []);
$columns = (array) ($displayData['fieldColumns'] ?? []);
$fields = (array) ($displayData['fields'] ?? $item->fields ?? []);
$part = static fn (string $name): string => (new FileLayout('components.dharmashop.product-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
$valueLayout = new FileLayout('components.dharmashop.fields.value', null, ['component' => 'none', 'client' => 0]);
?>
<tr class="dharmaCard dharmaCardRow">
    <td class="dharmaCardThumb">
        <a class="d-block" href="<?php echo $escape($productUrl); ?>" tabindex="-1" aria-hidden="true">
            <?php if ($images) : ?>
                <img class="rounded object-fit-cover" src="<?php echo $escape($images[0]->url); ?>" width="72" height="72" alt="" loading="lazy" decoding="async">
            <?php else : ?>
                <span class="d-inline-block rounded bg-body-tertiary p-4 align-middle" aria-hidden="true"></span>
            <?php endif; ?>
        </a>
    </td>
    <td class="dharmaCardTitle">
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
    </td>
    <?php foreach ($columns as $alias => $title) : ?>
        <td class="dharmaField" data-field="<?php echo $escape($alias); ?>">
            <?php $field = $fields[$alias] ?? null; ?>
            <?php if ($field && !empty($field->show) && !empty($field->values)) : ?><?php echo $valueLayout->render(['field' => $field]); ?><?php else : ?><span class="text-body-secondary">—</span><?php endif; ?>
        </td>
    <?php endforeach; ?>
    <td class="text-nowrap dharmaCardPriceCell"><?php echo $part('price'); ?></td>
    <td class="text-end text-nowrap dharmaCardActionsCell"><?php echo $part('actions'); ?></td>
</tr>
