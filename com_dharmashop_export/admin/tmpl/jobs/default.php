<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$this->getDocument()->getWebAssetManager()->useScript('multiselect');
?>
<form name="adminForm" id="adminForm" method="post" action="index.php?option=com_dharmashop_export&amp;view=jobs">
<table class="table"><thead><tr>
<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
<th>ID</th><th><?php echo Text::_('COM_DHARMASHOP_EXPORT_PROFILE'); ?></th><th><?php echo Text::_('JSTATUS'); ?></th><th><?php echo Text::_('JDATE'); ?></th>
<th><?php echo Text::_('COM_DHARMASHOP_EXPORT_WRITTEN'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_EXPORT_SIZE'); ?></th></tr></thead><tbody>
<?php foreach ($this->items as $i => $j) : ?>
<tr><td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $j->id); ?></td>
<td><a href="index.php?option=com_dharmashop_export&amp;view=job&amp;id=<?php echo (int) $j->id; ?>">#<?php echo (int) $j->id; ?></a></td>
<td><?php echo $this->escape((string) $j->title); ?></td>
<td><?php echo Text::_('COM_DHARMASHOP_EXPORT_STATUS_' . strtoupper($j->state)); ?></td>
<td><?php echo $this->escape($j->created); ?> UTC</td><td><?php echo (int) $j->written; ?></td>
<td><?php echo $j->artifact ? HTMLHelper::_('number.bytes', (int) $j->bytes) : '—'; ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$this->items) : ?><p class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p><?php endif; ?>
<?php echo $this->pagination->getListFooter(); ?><input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
