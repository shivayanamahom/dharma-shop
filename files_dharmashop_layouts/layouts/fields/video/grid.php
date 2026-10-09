<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/video/grid.php */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$classes = ['list-unstyled','row','g-3','mb-0','dharmaFieldVideo'];
foreach ($gallery['columns'] as $breakpoint=>$columns) { $classes[] = 'row-cols-' . ($breakpoint === 'xs' ? '' : $breakpoint . '-') . $columns; }
$itemLayout = new FileLayout('components.dharmashop.fields.video.item', null, ['component'=>'none','client'=>0]);
?>
<ul id="<?php echo htmlspecialchars($gallery['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="<?php echo htmlspecialchars(implode(' ', $classes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-dharmashop-field-gallery data-lightbox="<?php echo $gallery['lightbox'] ? '1' : '0'; ?>">
    <?php foreach ($field->values as $index=>$video) : ?>
        <li class="col dharmaFieldVideo__item"><?php echo $itemLayout->render($displayData + ['video'=>$video, 'index'=>$index]); ?></li>
    <?php endforeach; ?>
</ul>
