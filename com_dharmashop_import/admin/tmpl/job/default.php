<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
Factory::getApplication()->getLanguage()->load('com_dharmashop',JPATH_ADMINISTRATOR);
$wa=$this->getDocument()->getWebAssetManager();$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');$wa->useScript('com_dharmashop.admin-batch-progress');
foreach(['COM_DHARMASHOP_PROGRESS_BAR','COM_DHARMASHOP_PROGRESS_RUNNING','COM_DHARMASHOP_PROGRESS_DONE','COM_DHARMASHOP_PROGRESS_ERROR','COM_DHARMASHOP_PROGRESS_DISMISS','COM_DHARMASHOP_PROGRESS_RELOAD','COM_DHARMASHOP_PROGRESS_RESPONSE_ERROR','COM_DHARMASHOP_PROGRESS_FAILED','COM_DHARMASHOP_PROGRESS_RUNNING_HELP','COM_DHARMASHOP_PROGRESS_RECORDS','COM_DHARMASHOP_PROGRESS_REVIEW',
 'COM_DHARMASHOP_IMPORT_RESULT','COM_DHARMASHOP_IMPORT_ERRORS','COM_DHARMASHOP_IMPORT_APPLY','COM_DHARMASHOP_IMPORT_CONTINUE','COM_DHARMASHOP_IMPORT_REVIEW_HELP','COM_DHARMASHOP_IMPORT_REVIEW_ERRORS','COM_DHARMASHOP_IMPORT_ERRORS_CSV',
 'COM_DHARMASHOP_IMPORT_SUM_CREATE','COM_DHARMASHOP_IMPORT_SUM_UPDATE','COM_DHARMASHOP_IMPORT_SUM_UNCHANGED','COM_DHARMASHOP_IMPORT_SUM_ERRORS','COM_DHARMASHOP_IMPORT_RES_CREATE','COM_DHARMASHOP_IMPORT_RES_UPDATE','COM_DHARMASHOP_IMPORT_RES_UNCHANGED'] as $key){Text::script($key);}
$j=$this->job;$s=$this->summary;$user=$this->getCurrentUser();$running=in_array($j['state'],['reading','checking','media','finalizing','applying'],true);
$canCheck=$user->authorise('dharmashop.import.check','com_dharmashop_import');$canApply=$user->authorise('dharmashop.import.apply','com_dharmashop_import');
$skip=!empty($j['snapshot']['profile']['params']['skip_errors']);$done=$j['state']==='done';$base='index.php?option=com_dharmashop_import&view=job&id='.(int)$j['id'];
$kind=$done?'RES':'SUM';
?>
<h2 class="h4"><?php echo $this->escape($j['snapshot']['profile']['title']); ?></h2>
<p class="alert alert-<?php echo $j['state']==='failed'?'danger':($done?'success':'info'); ?>"><?php echo Text::_('COM_DHARMASHOP_IMPORT_PHASE_'.strtoupper($j['state'])); ?> · <?php echo (int)$j['processed'].' / '.(int)$j['total']; ?>
<?php if($s['rows']): ?><br><?php echo Text::_('COM_DHARMASHOP_IMPORT_'.$kind.'_CREATE'); ?>: <strong><?php echo (int)$s['create']; ?></strong> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_'.$kind.'_UPDATE'); ?>: <strong><?php echo (int)$s['update']; ?></strong> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_'.$kind.'_UNCHANGED'); ?>: <strong><?php echo (int)$s['unchanged']; ?></strong> · <?php echo Text::_('COM_DHARMASHOP_IMPORT_SUM_ERRORS'); ?>: <strong><?php echo (int)$s['errors']; ?></strong><?php endif; ?></p>
<?php if($j['error']): ?><p class="alert alert-danger"><?php echo $this->escape(Text::_($j['error'])); ?></p><?php endif; ?>
<form id="adminForm" name="adminForm" method="post" action="index.php?option=com_dharmashop_import">
<input type="hidden" name="id" value="<?php echo (int)$j['id']; ?>"><?php echo HTMLHelper::_('form.token'); ?>
<div class="d-flex flex-wrap gap-2 mb-3">
<?php if(($running||$j['state']==='failed')&&($j['state']==='applying'||($j['snapshot']['resume_phase']??'')==='applying'?$canApply:$canCheck)): ?><button class="btn btn-primary" type="button" data-dharmashop-import-run="<?php echo (int)$j['id']; ?>" data-import-title="<?php echo $this->escape($j['snapshot']['profile']['title']); ?>" <?php if(Factory::getApplication()->getInput()->getInt('run')===1): ?>data-dharmashop-import-auto<?php endif; ?>><span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_IMPORT_CONTINUE'); ?></button><?php endif; ?>
<?php if($j['state']==='checked'): ?>
<?php if($canApply&&(!$j['errors']||$skip)): ?><button class="btn btn-success" type="button" data-dharmashop-import-run="<?php echo (int)$j['id']; ?>" data-import-title="<?php echo $this->escape($j['snapshot']['profile']['title']); ?>"><span class="icon-check" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_IMPORT_APPLY'); ?></button>
<?php else: ?><span class="text-danger align-self-center"><?php echo Text::_('COM_DHARMASHOP_IMPORT_REVIEW_ERRORS'); ?></span><?php endif; ?>
<?php endif; ?>
<?php if(!in_array($j['state'],['done','cancelled'],true)&&($j['state']==='applying'?$canApply:$canCheck)): ?><button class="btn btn-outline-danger" name="task" value="job.cancel" type="submit"><?php echo Text::_('COM_DHARMASHOP_IMPORT_CANCEL'); ?></button><?php endif; ?>
</div>
</form>
<?php if($j['state']==='checked'): ?><p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_IMPORT_REVIEW_HELP'); ?></p><?php endif; ?>
<ul class="nav nav-pills mb-3">
<?php foreach([''=>'ALL','error'=>'ERROR','create'=>'CREATE','update'=>'UPDATE','unchanged'=>'UNCHANGED'] as $value=>$name): ?>
<li class="nav-item"><a class="nav-link<?php echo $this->filter===$value?' active':''; ?>" href="<?php echo $base.($value!==''?'&filter='.$value:''); ?>"><?php echo Text::_('COM_DHARMASHOP_IMPORT_FILTER_'.$name); ?></a></li>
<?php endforeach; ?></ul>
<table class="table"><thead><tr><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_LINE'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_KEY'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_ACTION'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_IMPORT_CHANGES'); ?></th></tr></thead><tbody>
<?php foreach($this->rows as $row): $p=json_decode($row['payload'],true)?:[]; ?><tr><td><?php echo (int)$row['line']; ?></td><td><?php echo $this->escape($row['source_key']); ?></td><td><?php echo $row['action']?Text::_('COM_DHARMASHOP_IMPORT_ROW_'.strtoupper($row['action'])):$this->escape($row['state']); ?><?php if($row['target_id']): ?> #<?php echo (int)$row['target_id']; ?><?php endif; ?></td><td>
<?php if($row['error']): ?><span class="text-danger"><?php echo $this->escape(Text::_($row['error'])); ?></span><?php elseif(isset($p['patch'])): ?>
<details><summary><?php echo Text::_('COM_DHARMASHOP_IMPORT_CHANGES'); ?></summary><dl>
<?php foreach($p['patch'] as $key=>$value): if($key==='custom_fields_present'){continue;}if($key==='__new_fields'){$key='COM_DHARMASHOP_IMPORT_CREATE_MISSING_FIELDS';$value=array_map(static fn($f)=>['title'=>$f['definition']['title'],'type'=>$f['definition']['type'],'options'=>$f['options']],$value);}elseif($key==='__category_chain'){$key='COM_DHARMASHOP_IMPORT_CREATE_MISSING_CATEGORIES';$value=array_column($value,'title');} ?><dt><?php echo $this->escape(Text::_($key)); ?></dt><dd><del><?php echo $this->escape(json_encode($p['before'][$key]??null,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)); ?></del> → <ins><?php echo $this->escape(json_encode($value,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)); ?></ins></dd><?php endforeach; ?></dl></details>
<?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php if(!$this->rows): ?><p class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p><?php endif; ?>
<?php if(count($this->rows)===100): ?><a class="btn btn-outline-primary" href="<?php echo $base.($this->filter!==''?'&filter='.$this->filter:'').'&after='.(int)end($this->rows)['id']; ?>"><?php echo Text::_('JNEXT'); ?></a><?php endif; ?>
<?php echo LayoutHelper::render('batch-progress',[],JPATH_ADMINISTRATOR.'/components/com_dharmashop/layouts'); ?>
