<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$this->getDocument()->getWebAssetManager()->useScript('multiselect');
?>
<form name="adminForm" id="adminForm" method="post" action="index.php?option=com_dharmashop_import&amp;view=jobs">
<table class="table"><thead><tr>
<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
<th>ID</th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_PROFILE'); ?></th><th><?php echo Text::_('JSTATUS'); ?></th><th><?php echo Text::_('JDATE'); ?></th>
<th><?php echo Text::_('COM_DHARMASHOP_IMPORT_RESULT'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_ERRORS'); ?></th></tr></thead><tbody>
<?php foreach ($this->items as $i => $item) : ?>
<tr><td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
<td><a href="index.php?option=com_dharmashop_import&amp;view=job&amp;id=<?php echo (int) $item->id; ?>">#<?php echo (int) $item->id; ?></a></td>
<td><?php echo $this->escape((string) $item->title); ?></td>
<td><?php echo Text::_('COM_DHARMASHOP_IMPORT_PHASE_' . strtoupper($item->state)); ?></td>
<td><?php echo $this->escape($item->created); ?> UTC</td>
<td><?php if ($item->state === 'done') : echo Text::_('COM_DHARMASHOP_IMPORT_RES_CREATE') . ': ' . (int) $item->created_count . ' · ' . Text::_('COM_DHARMASHOP_IMPORT_RES_UPDATE') . ': ' . (int) $item->updated_count . ' · ' . Text::_('COM_DHARMASHOP_IMPORT_RES_UNCHANGED') . ': ' . (int) $item->unchanged_count; else : echo (int) $item->processed . ' / ' . (int) $item->total; endif; ?></td>
<td><?php echo (int) $item->errors; ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$this->items) : ?><p class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p><?php endif; ?>
<?php echo $this->pagination->getListFooter(); ?><input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
