<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

use Dharma\Component\DharmaShopExport\Administrator\Export\Cells;
$p=$this->preview['profile'];$cols=$p['params']['columns'];
?>
<p class="alert alert-info"><?php echo Text::sprintf('COM_DHARMASHOP_EXPORT_PREVIEW_INFO',(int)$this->preview['total']); ?></p>
<div class="table-responsive"><table class="table"><thead><tr><?php foreach($cols as $col): ?><th><?php echo $this->escape($col['header']); ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($this->preview['rows'] as $row): ?><tr><?php foreach($cols as $col): ?><td><?php echo $this->escape(Cells::text($row[$col['key']]??null,$col['mode'],$p['params']['list_separator'])); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<form method="post" action="index.php?option=com_dharmashop_export"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-primary" name="task" value="job.start"><?php echo Text::_('COM_DHARMASHOP_EXPORT_RUN'); ?></button><?php echo HTMLHelper::_('form.token'); ?></form>
