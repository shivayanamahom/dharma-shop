<?php
/** Override: html/layouts/components/dharmashop/fields/gallery/grid.php */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$field = $displayData['field'];
$gallery = $displayData['gallery'];
$classes = ['list-unstyled', 'row', 'g-3', 'mb-0', 'dharmaFieldGallery'];
foreach ($gallery['columns'] as $breakpoint => $columns) {
    $classes[] = 'row-cols-' . ($breakpoint === 'xs' ? '' : $breakpoint . '-') . $columns;
}
$itemLayout = new FileLayout('components.dharmashop.fields.gallery.image', null, ['component'=>'none', 'client'=>0]);
?>
<ul id="<?php echo $gallery['id']; ?>" class="<?php echo implode(' ', $classes); ?>" data-dharmashop-field-gallery data-lightbox="<?php echo $gallery['lightbox'] ? '1' : '0'; ?>">
    <?php foreach ($field->values as $image) : ?>
        <li class="col dharmaFieldGallery__item">
            <?php echo $itemLayout->render($displayData + ['image'=>$image]); ?>
        </li>
    <?php endforeach; ?>
</ul>
