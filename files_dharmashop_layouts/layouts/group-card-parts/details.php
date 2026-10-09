<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$swatch = new FileLayout('components.dharmashop.fields.swatch', null, ['component' => 'none', 'client' => 0]);
?>
<?php if ($displayData['productCount'] !== null) : ?>
    <p class="small text-body-secondary mt-2 mb-0 dharmaGroupCount"><?php echo $escape(Text::sprintf('COM_DHARMASHOP_GROUP_CARD_COUNT', $displayData['productCount'])); ?></p>
<?php endif; ?>
<?php foreach ($displayData['choiceFields'] as $field) : $field = (object) $field; ?>
    <div class="mt-2 dharmaGroupChoices">
        <div class="small text-body-secondary"><?php echo $escape($field->title ?? ''); ?></div>
        <div class="d-flex flex-wrap align-items-center gap-2">
        <?php foreach ((array) ($field->values ?? []) as $value) : $value = (object) $value; ?>
            <span class="d-inline-flex align-items-center gap-1 small" title="<?php echo $escape($value->title ?? ''); ?>">
                <?php if (($field->type ?? '') === 'color') : ?>
                    <?php echo $swatch->render(['color' => $value->color ?? '', 'image' => $value->image ?? '', 'label' => $value->title ?? '', 'context' => 'group.choice', 'round' => true]); ?>
                <?php endif; ?>
                <span><?php echo $escape($value->title ?? ''); ?></span>
            </span>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
