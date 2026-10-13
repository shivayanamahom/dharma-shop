<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

use Dharma\Component\DharmaShopExport\Administrator\Export\Files;
$j=$this->job;$s=$j['snapshot'];$p=$s['profile'];$canRun=$this->getCurrentUser()->authorise('dharmashop.export.run','com_dharmashop_export');
?>
<div class="card"><div class="card-body">
<h2><?php echo $this->escape($p['title']); ?></h2><p><?php echo Text::_('COM_DHARMASHOP_EXPORT_STATUS_'.strtoupper($j['state'])); ?></p>
<progress class="w-100" max="<?php echo max(1,(int)$j['total']); ?>" value="<?php echo (int)$j['processed']; ?>"><?php echo (int)$j['processed']; ?></progress>
<dl class="row"><dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_EXPORT_PROCESSED'); ?></dt><dd class="col-sm-8"><?php echo (int)$j['processed'].' / '.(int)$j['total']; ?></dd><dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_EXPORT_WRITTEN'); ?></dt><dd class="col-sm-8"><?php echo (int)$j['written']; ?></dd><dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_EXPORT_SKIPPED'); ?></dt><dd class="col-sm-8"><?php echo (int)$j['skipped']; ?></dd><dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_EXPORT_CREATED'); ?></dt><dd class="col-sm-8"><?php echo $this->escape($j['created']); ?> UTC</dd><dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_EXPORT_FINISHED'); ?></dt><dd class="col-sm-8"><?php echo $this->escape((string)$j['finished']); ?><?php if($j['finished']): ?> UTC<?php endif; ?></dd></dl>
<?php foreach($j['issues'] as $reason=>$issue): ?><p class="alert alert-warning"><?php echo $this->escape(Text::_($reason)).': '.(int)$issue['count'].' · ID: '.$this->escape(implode(', ',$issue['ids'])); ?></p><?php endforeach; ?>
<?php if($j['error']): ?><div class="alert alert-danger"><?php echo $this->escape(Text::_($j['error'])); ?></div><?php endif; ?>
<?php if($j['state']==='done'): ?>
<p><?php echo (int)$j['bytes']; ?> B · SHA-256: <code><?php echo $this->escape($j['sha256']); ?></code></p>
<?php if($j['artifact']): ?><a class="btn btn-primary" href="index.php?option=com_dharmashop_export&amp;task=job.download&amp;id=<?php echo (int)$j['id'].'&amp;'.Session::getFormToken(); ?>=1"><?php echo Text::_('COM_DHARMASHOP_EXPORT_DOWNLOAD'); ?></a><?php else: ?><p><?php echo Text::_('COM_DHARMASHOP_EXPORT_NO_FILE'); ?></p><?php endif; ?>
<?php if($p['params']['visibility']==='public'): $url=(new Files($s['storage']))->publicUrl($j); ?><p class="mt-3"><?php echo Text::_('COM_DHARMASHOP_EXPORT_PUBLIC_URL'); ?> <a href="<?php echo $this->escape($url); ?>"><?php echo $this->escape($url); ?></a></p><?php endif; ?>
<?php endif; ?>
<?php if($canRun && in_array($j['state'],['running','failed'],true)): ?>
<form method="post" action="index.php?option=com_dharmashop_export"><input type="hidden" name="id" value="<?php echo (int)$j['id']; ?>"><?php echo HTMLHelper::_('form.token'); ?>
<?php if($j['state']==='failed'): ?><button class="btn btn-primary" name="task" value="job.resume"><?php echo Text::_('COM_DHARMASHOP_EXPORT_RESUME'); ?></button><?php endif; ?>
<button class="btn btn-outline-danger" name="task" value="job.cancel"><?php echo Text::_('JCANCEL'); ?></button></form><?php endif; ?>
<?php if($canRun && in_array($j['state'],['done','cancelled'],true)): ?><form class="mt-3" method="post" action="index.php?option=com_dharmashop_export"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-outline-primary" name="task" value="job.start"><?php echo Text::_('COM_DHARMASHOP_EXPORT_REPEAT'); ?></button><?php echo HTMLHelper::_('form.token'); ?></form><?php endif; ?>
</div></div>
