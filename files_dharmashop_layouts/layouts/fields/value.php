<?php
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

use Joomla\CMS\Filter\InputFilter;

$field = $displayData['field'];
$values = (array) $field->values;
$format = (string) $field->format;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$filter = new InputFilter(['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'span', 'h2', 'h3', 'blockquote'], ['href', 'title', 'class']);
$text = static fn (object $value): string => !empty($value->html)
    ? $filter->clean((string) $value->text, 'HTML') : $escape($value->text);
?>
<?php if ($format === 'list') : ?>
    <ul class="ps-3 mb-0">
        <?php foreach ($values as $value) : ?><li><?php echo $text($value); ?></li><?php endforeach; ?>
    </ul>
<?php elseif (in_array($format, ['image', 'image_title'], true)) : ?>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <?php foreach ($values as $value) : ?>
            <?php $label = trim(strip_tags((string) $value->text)); if (!empty($value->html)) { $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'); } ?>
            <span class="d-inline-flex align-items-center gap-1">
                <?php if ($value->image !== '') : ?>
                    <img class="rounded object-fit-contain" src="<?php echo $escape(ImageUrl::resolve($value->image, 'field.option')); ?>" width="48" height="48" alt="<?php echo $format === 'image' ? $escape($label) : ''; ?>" title="<?php echo $escape($label); ?>" loading="lazy" decoding="async">
                <?php endif; ?>
                <?php if ($format === 'image_title' || $value->image === '') : ?>
                    <span><?php echo $escape($label); ?></span>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <?php foreach ($values as $index => $value) : ?><?php echo $index > 0 ? ', ' : ''; ?><?php echo $text($value); ?><?php endforeach; ?>
<?php endif; ?>
