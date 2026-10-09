<?php
/** Строка группы в той же таблице, что и товары; число ячеек соответствует её заголовку. */
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$part = static fn(string $name): string => (new FileLayout('components.dharmashop.group-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
?>
<tr class="dharmaGroupCard dharmaGroupCardRow" data-dharmashop-group="<?php echo (int) $displayData['group']->id; ?>">
    <td class="w-100">
        <div class="d-flex gap-3">
            <a class="flex-shrink-0" href="<?php echo $escape($displayData['url']); ?>" tabindex="-1" aria-hidden="true">
                <?php if ($displayData['image'] !== '') : ?><img class="rounded object-fit-cover" src="<?php echo $escape(ImageUrl::resolve($displayData['image'], $displayData['imageContext'])); ?>" width="56" height="56" alt="" loading="lazy" decoding="async"><?php else : ?><span class="d-block rounded bg-body-tertiary p-4"></span><?php endif; ?>
            </a>
            <div><a class="fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($displayData['title']); ?></a><?php echo $part('details'); ?></div>
        </div>
    </td>
    <?php foreach ($displayData['fieldColumns'] as $alias => $title) : ?><td class="d-none d-lg-table-cell" data-field="<?php echo $escape($alias); ?>"><span class="text-body-secondary">—</span></td><?php endforeach; ?>
    <td class="text-end dharmaGroupFooter"><?php echo $part('actions'); ?></td>
</tr>
