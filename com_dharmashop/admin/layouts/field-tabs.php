<?php

/**
 * Dharma Shop — нативные вкладки дополнительных полей товара/категории.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Support\FieldGroupTabs;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var array{form: Form, groups: array<int, object>, tabSet: string} $displayData */
$form = $displayData['form'];
$tabSet = $displayData['tabSet'];
$shared = [];
$separate = [];

foreach ($displayData['groups'] as $group) {
    if ($group->tab_mode === 'group') {
        $separate[] = $group;
    } else {
        $shared[] = $group;
    }
}

// Поля без группы принадлежат общей вкладке; нет данных — нет пустой общей вкладки.
$ungrouped = FieldGroupTabs::fieldset(0);
$hasUngrouped = (bool) $form->getFieldset($ungrouped);
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($shared || $hasUngrouped) : ?>
    <?php echo HTMLHelper::_('uitab.addTab', $tabSet, 'fields', Text::_('COM_DHARMASHOP_TAB_FIELDS')); ?>
    <?php if (!empty($displayData['hint'])) : ?><p class="text-muted"><?php echo Text::_($displayData['hint']); ?></p><?php endif; ?>
    <?php if ($hasUngrouped) : ?>
        <?php echo $form->renderFieldset($ungrouped); ?>
    <?php endif; ?>
    <?php foreach ($shared as $group) : ?>
        <fieldset class="options-form">
            <legend><?php echo $escape((string) $group->title); ?></legend>
            <?php $fieldset = FieldGroupTabs::fieldset((int) $group->id); ?>
            <?php if ($form->getFieldset($fieldset)) : ?>
                <?php echo $form->renderFieldset($fieldset); ?>
            <?php else : ?>
                <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_FIELDGROUP_EMPTY'); ?></p>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>

<?php foreach ($separate as $group) : ?>
    <?php echo HTMLHelper::_('uitab.addTab', $tabSet, 'field-group-' . (int) $group->id, (string) $group->title); ?>
    <?php if (!empty($displayData['hint'])) : ?><p class="text-muted"><?php echo Text::_($displayData['hint']); ?></p><?php endif; ?>
    <?php $fieldset = FieldGroupTabs::fieldset((int) $group->id); ?>
    <?php if ($form->getFieldset($fieldset)) : ?>
        <?php echo $form->renderFieldset($fieldset); ?>
    <?php else : ?>
        <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_FIELDGROUP_EMPTY'); ?></p>
    <?php endif; ?>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endforeach; ?>
