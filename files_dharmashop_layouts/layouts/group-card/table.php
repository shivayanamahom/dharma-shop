<?php
/** Строка группы в той же таблице, что и товары; число ячеек соответствует её заголовку. */
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$part = static fn(string $name): string => (new FileLayout('components.dharmashop.group-card-parts.' . $name, null, ['component' => 'none', 'client' => 0]))->render($displayData);
?>
<tr class="dharmaGroupCard dharmaGroupCardRow" <?php echo $part('attributes'); ?>>
    <td class="w-100">
        <div class="d-flex gap-3">
            <?php echo $part('media'); ?>
            <div><a class="fw-semibold link-body-emphasis text-decoration-none" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape($displayData['title']); ?></a><?php echo $part('details'); ?></div>
        </div>
    </td>
    <?php foreach ($displayData['fieldColumns'] as $alias => $title) : ?><td class="d-none d-lg-table-cell" data-field="<?php echo $escape($alias); ?>"><span class="text-body-secondary">—</span></td><?php endforeach; ?>
    <td class="text-end dharmaGroupFooter"><?php echo $part('actions'); ?></td>
</tr>
