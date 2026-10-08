<?php

/** Краткое описание товара и только специально включённые для интро характеристики. */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$fields = array_filter((array) ($this->item->fields ?? []), static fn (object $field): bool => !empty($field->show_intro) && !empty($field->values));
?>
<?php if (trim((string) ($this->item->introtext ?? '')) !== '') : ?>
    <div class="mb-4 dharmaIntro"><?php echo $this->text((string) $this->item->introtext); ?></div>
<?php endif; ?>
<?php echo $this->renderHook('beforeIntroFields'); ?>
<?php if ($fields) : ?>
    <div class="dharmaProductIntroFields">
        <?php echo (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $fields, 'context' => 'intro']); ?>
    </div>
<?php endif; ?>
<?php echo $this->renderHook('afterIntroFields'); ?>
