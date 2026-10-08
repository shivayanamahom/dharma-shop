<?php

/** Описание и группы характеристик: штатные вкладки Joomla с мобильным аккордионом. */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$description = trim((string) ($this->item->fulltext ?? ''));
$groups = $this->fieldGroups;
if ($description === '' && !$groups) {
    return;
}

$tabset = 'dharmaProductTabs-' . (int) $this->item->id;
$active = $description !== '' ? $tabset . '-description' : $tabset . '-group-' . array_key_first($groups);
$fieldsLayout = new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]);
?>
<div class="mt-5 dharmaProductDetails">
    <?php echo HTMLHelper::_('uitab.startTabSet', $tabset, ['active' => $active, 'breakpoint' => 768]); ?>
    <?php if ($description !== '') : ?>
        <?php echo HTMLHelper::_('uitab.addTab', $tabset, $tabset . '-description', Text::_('COM_DHARMASHOP_PRODUCT_DESCRIPTION')); ?>
        <div class="dharmaFulltext"><?php echo $this->text($description); ?></div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php endif; ?>
    <?php foreach ($groups as $id => $group) : ?>
        <?php
        $title = $group['title'] !== '' ? $group['title'] : Text::_('COM_DHARMASHOP_PRODUCT_CHARACTERISTICS');
        // Компонент Joomla вставляет name через innerHTML: сохраняем название как обычный текст.
        echo HTMLHelper::_('uitab.addTab', $tabset, $tabset . '-group-' . (int) $id, $this->escape($title));
        ?>
        <div class="dharmaProductFieldGroup" data-group="<?php echo (int) $id; ?>">
            <?php echo $fieldsLayout->render(['fields' => $group['fields'], 'context' => 'page']); ?>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php endforeach; ?>
    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
</div>
