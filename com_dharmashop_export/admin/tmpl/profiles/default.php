<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;

// Окно прогресса и скрипт общие с пакетной обработкой и командами магазина (com_dharmashop).
Factory::getApplication()->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
foreach (['COM_DHARMASHOP_PROGRESS_BAR', 'COM_DHARMASHOP_PROGRESS_RUNNING', 'COM_DHARMASHOP_PROGRESS_DONE', 'COM_DHARMASHOP_PROGRESS_ERROR', 'COM_DHARMASHOP_PROGRESS_DISMISS', 'COM_DHARMASHOP_PROGRESS_RELOAD',
    'COM_DHARMASHOP_PROGRESS_RESPONSE_ERROR', 'COM_DHARMASHOP_PROGRESS_FAILED', 'COM_DHARMASHOP_PROGRESS_RUNNING_HELP', 'COM_DHARMASHOP_PROGRESS_RECORDS', 'COM_DHARMASHOP_EXPORT_PROGRESS_DONE_HELP',
    'COM_DHARMASHOP_EXPORT_PROGRESS_WRITTEN', 'COM_DHARMASHOP_EXPORT_PROGRESS_SKIPPED', 'COM_DHARMASHOP_EXPORT_PROGRESS_DOWNLOAD', 'COM_DHARMASHOP_EXPORT_PROGRESS_FEED', 'COM_DHARMASHOP_EXPORT_PROGRESS_OPEN_JOB'] as $key) {
    Text::script($key);
}
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
$wa->useScript('multiselect')->useScript('com_dharmashop.admin-batch-progress');
$user = $this->getCurrentUser();
$canRun = $user->authorise('dharmashop.export.run', 'com_dharmashop_export');
$canSee = $canRun || $user->authorise('dharmashop.export.report', 'com_dharmashop_export');
$canSchedule = $user->authorise('core.create', 'com_scheduler') && PluginHelper::isEnabled('task', 'dharmashopexport');
$token = Session::getFormToken();
?>
<form id="adminForm" name="adminForm" method="post" action="index.php?option=com_dharmashop_export&amp;view=profiles">
<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
<table class="table"><thead><tr>
<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
<th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
<th><?php echo Text::_('COM_DHARMASHOP_EXPORT_ENTITY'); ?></th>
<th><?php echo Text::_('COM_DHARMASHOP_EXPORT_FORMAT'); ?></th>
<th><?php echo Text::_('JSTATUS'); ?></th>
<?php if ($canRun) : ?><th><?php echo Text::_('COM_DHARMASHOP_EXPORT_LIST_RUN'); ?></th><?php endif; ?>
<?php if ($canSee) : ?><th><?php echo Text::_('COM_DHARMASHOP_EXPORT_LIST_FILE'); ?></th><?php endif; ?>
<?php if ($canSchedule) : ?><th><?php echo Text::_('COM_DHARMASHOP_EXPORT_LIST_SCHEDULE'); ?></th><?php endif; ?>
<th class="w-1">ID</th></tr></thead><tbody>
<?php foreach ($this->items as $i => $item) : ?>
<tr>
<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
<th scope="row"><?php if ($user->authorise('core.edit', 'com_dharmashop_export')) : ?><a href="index.php?option=com_dharmashop_export&amp;task=profile.edit&amp;id=<?php echo (int) $item->id; ?>"><?php echo $this->escape($item->title); ?></a><?php else : echo $this->escape($item->title); endif; ?></th>
<td><?php echo Text::_('COM_DHARMASHOP_EXPORT_' . strtoupper($item->entity)); ?></td>
<td><?php echo $this->escape(strtoupper($item->format)); ?></td>
<td><?php echo Text::_($item->state ? 'JENABLED' : 'JDISABLED'); ?></td>
<?php if ($canRun) : ?><td>
<?php if ($item->state) : ?><button type="button" class="btn btn-sm btn-primary" data-dharmashop-export-run="<?php echo (int) $item->id; ?>" data-export-title="<?php echo $this->escape($item->title); ?>"><span class="icon-play" aria-hidden="true"></span> <?php echo Text::_($item->active ? 'COM_DHARMASHOP_EXPORT_CONTINUE' : 'COM_DHARMASHOP_EXPORT_START'); ?></button><?php endif; ?>
</td><?php endif; ?>
<?php if ($canSee) : ?><td>
<?php if ($item->last) : ?>
<a class="btn btn-sm btn-outline-primary" href="index.php?option=com_dharmashop_export&amp;task=job.download&amp;id=<?php echo (int) $item->last['id']; ?>&amp;<?php echo $token; ?>=1"><span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_EXPORT_DOWNLOAD_SHORT'); ?></a>
<small class="d-block text-muted"><?php echo HTMLHelper::_('date', $item->last['finished'], Text::_('DATE_FORMAT_LC5')); ?> · <?php echo HTMLHelper::_('number.bytes', $item->last['bytes']); ?></small>
<?php if ($item->last['public'] !== '') : ?>
<label class="visually-hidden" for="export-url-<?php echo (int) $item->id; ?>"><?php echo Text::_('COM_DHARMASHOP_EXPORT_FEED_LINK'); ?></label>
<input type="text" readonly class="form-control form-control-sm mt-1" id="export-url-<?php echo (int) $item->id; ?>" value="<?php echo $this->escape($item->last['public']); ?>" title="<?php echo Text::_('COM_DHARMASHOP_EXPORT_FEED_LINK'); ?>" onfocus="this.select()">
<a class="small" href="<?php echo $this->escape($item->last['public']); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_DHARMASHOP_EXPORT_FEED_LINK'); ?></a>
<?php endif; ?>
<?php else : ?><span class="text-muted"><?php echo Text::_('COM_DHARMASHOP_EXPORT_NEVER'); ?></span><?php endif; ?>
<?php if ($item->feed && (!$item->last || $item->last['public'] === '')) : ?><small class="d-block text-muted"><?php echo Text::_('COM_DHARMASHOP_EXPORT_FEED_AFTER_RUN'); ?></small><?php endif; ?>
</td><?php endif; ?>
<?php if ($canSchedule) : ?><td>
<?php if ($item->state) : ?><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="index.php?option=com_dharmashop_export&amp;task=profile.schedule&amp;id=<?php echo (int) $item->id; ?>&amp;<?php echo $token; ?>=1" title="<?php echo Text::_('COM_DHARMASHOP_EXPORT_ADD_SCHEDULE_DESC'); ?>"><?php echo Text::_('COM_DHARMASHOP_EXPORT_ADD_SCHEDULE'); ?></a><?php endif; ?>
</td><?php endif; ?>
<td><?php echo (int) $item->id; ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$this->items) : ?><p class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p><?php endif; ?>
<?php echo $this->pagination->getListFooter(); ?><input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
<?php echo LayoutHelper::render('batch-progress', [], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>
