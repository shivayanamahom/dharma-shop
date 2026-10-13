<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

// Окно прогресса и скрипт общие с экспортом, пакетной обработкой и командами магазина (com_dharmashop).
Factory::getApplication()->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
foreach (['COM_DHARMASHOP_PROGRESS_BAR', 'COM_DHARMASHOP_PROGRESS_RUNNING', 'COM_DHARMASHOP_PROGRESS_DONE', 'COM_DHARMASHOP_PROGRESS_ERROR', 'COM_DHARMASHOP_PROGRESS_DISMISS', 'COM_DHARMASHOP_PROGRESS_RELOAD',
    'COM_DHARMASHOP_PROGRESS_RESPONSE_ERROR', 'COM_DHARMASHOP_PROGRESS_FAILED', 'COM_DHARMASHOP_PROGRESS_RUNNING_HELP', 'COM_DHARMASHOP_PROGRESS_CONFIRM','COM_DHARMASHOP_PROGRESS_DONE_HELP','COM_DHARMASHOP_PROGRESS_RECORDS', 'COM_DHARMASHOP_PROGRESS_REVIEW',
    'COM_DHARMASHOP_IMPORT_RESULT', 'COM_DHARMASHOP_IMPORT_ERRORS', 'COM_DHARMASHOP_IMPORT_APPLY', 'COM_DHARMASHOP_IMPORT_CONTINUE', 'COM_DHARMASHOP_IMPORT_REVIEW_HELP', 'COM_DHARMASHOP_IMPORT_REVIEW_SKIP','COM_DHARMASHOP_IMPORT_REVIEW_ERRORS',
    'COM_DHARMASHOP_IMPORT_ERRORS_CSV', 'COM_DHARMASHOP_IMPORT_SUM_CREATE', 'COM_DHARMASHOP_IMPORT_SUM_UPDATE', 'COM_DHARMASHOP_IMPORT_SUM_UNCHANGED', 'COM_DHARMASHOP_IMPORT_SUM_ERRORS',
    'COM_DHARMASHOP_IMPORT_RES_CREATE', 'COM_DHARMASHOP_IMPORT_RES_UPDATE', 'COM_DHARMASHOP_IMPORT_RES_UNCHANGED'] as $key) {
    Text::script($key);
}
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
$wa->useScript('multiselect')->useScript('com_dharmashop.admin-batch-progress');
$user = $this->getCurrentUser();
$canCheck = $user->authorise('dharmashop.import.check', 'com_dharmashop_import');
$canApply = $user->authorise('dharmashop.import.apply', 'com_dharmashop_import');
$running = ['reading', 'checking', 'media', 'finalizing', 'applying'];
?>
<form id="adminForm" name="adminForm" method="post" action="index.php?option=com_dharmashop_import&amp;view=profiles">
<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
<table class="table"><thead><tr>
<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
<th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
<th><?php echo Text::_('COM_DHARMASHOP_IMPORT_ENTITY'); ?></th>
<th><?php echo Text::_('JSTATUS'); ?></th>
<?php if ($canCheck || $canApply) : ?><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_LIST_RUN'); ?></th><?php endif; ?>
<th><?php echo Text::_('COM_DHARMASHOP_IMPORT_RESULT'); ?></th>
<th class="w-1">ID</th></tr></thead><tbody>
<?php foreach ($this->items as $i => $item) :
    $last = $item->last;
    $state = $last['state'] ?? '';
    $title = $this->escape($item->title);
    ?>
<tr>
<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
<th scope="row"><?php if ($user->authorise('core.edit', 'com_dharmashop_import')) : ?><a href="index.php?option=com_dharmashop_import&amp;task=profile.edit&amp;id=<?php echo (int) $item->id; ?>"><?php echo $title; ?></a><?php else : echo $title; endif; ?></th>
<td><?php echo Text::_('COM_DHARMASHOP_IMPORT_' . strtoupper($item->entity)); ?></td>
<td><?php echo Text::_($item->state ? 'JENABLED' : 'JDISABLED'); ?></td>
<?php if ($canCheck || $canApply) : ?><td>
<?php if ($item->state && $last && (in_array($state, $running, true) || $state === 'failed') && $canCheck) : ?>
<button type="button" class="btn btn-sm btn-primary" data-dharmashop-import-run="<?php echo (int) $last['id']; ?>" data-import-title="<?php echo $title; ?>"><span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_IMPORT_CONTINUE'); ?></button>
<?php elseif ($item->state && $last && $state === 'checked' && $canCheck) : ?>
<button type="button" class="btn btn-sm btn-success" data-dharmashop-import-run="<?php echo (int) $last['id']; ?>" data-import-title="<?php echo $title; ?>"><span class="icon-check" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_IMPORT_REVIEW'); ?></button>
<button type="button" class="btn btn-sm btn-outline-secondary" data-dharmashop-import-start="<?php echo (int) $item->id; ?>" data-import-title="<?php echo $title; ?>"><?php echo Text::_('COM_DHARMASHOP_IMPORT_RECHECK'); ?></button>
<?php elseif ($item->state && $canCheck) : ?>
<button type="button" class="btn btn-sm btn-primary" data-dharmashop-import-start="<?php echo (int) $item->id; ?>" data-import-title="<?php echo $title; ?>"><span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_IMPORT_CHECK'); ?></button>
<?php endif; ?>
</td><?php endif; ?>
<td>
<?php if ($last) : ?>
<a href="index.php?option=com_dharmashop_import&amp;view=job&amp;id=<?php echo (int) $last['id']; ?>"><?php echo Text::_('COM_DHARMASHOP_IMPORT_PHASE_' . strtoupper($state)); ?></a>
<?php if ($state === 'done') : ?><small class="d-block text-muted"><?php echo Text::_('COM_DHARMASHOP_IMPORT_RES_CREATE'); ?>: <?php echo (int) $last['created_count']; ?> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_RES_UPDATE'); ?>: <?php echo (int) $last['updated_count']; ?> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_RES_UNCHANGED'); ?>: <?php echo (int) $last['unchanged_count']; ?><?php if ((int) $last['errors']) : ?> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_ERRORS'); ?>: <?php echo (int) $last['errors']; ?><?php endif; ?></small>
<small class="d-block text-muted"><?php echo HTMLHelper::_('date', $last['finished'] ?: $last['created'], Text::_('DATE_FORMAT_LC5')); ?></small>
<?php elseif ($state === 'checked') : ?><small class="d-block text-muted"><?php echo Text::_('COM_DHARMASHOP_IMPORT_WAITING_APPLY'); ?></small>
<?php endif; ?>
<?php else : ?><span class="text-muted"><?php echo Text::_('COM_DHARMASHOP_IMPORT_NEVER'); ?></span><?php endif; ?>
</td>
<td><?php echo (int) $item->id; ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$this->items) : ?><p class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p><?php endif; ?>
<?php echo $this->pagination->getListFooter(); ?><input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
<?php echo LayoutHelper::render('batch-progress', [], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>
