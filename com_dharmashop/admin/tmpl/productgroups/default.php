<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=productgroups'); ?>" method="post" name="adminForm" id="adminForm">
<?php echo LayoutHelper::render('joomla.searchtools.default',['view'=>$this]); ?>
<table class="table"><thead><tr><td><?php echo HTMLHelper::_('grid.checkall'); ?></td><th><?php echo Text::_('JSTATUS'); ?></th><th><?php echo Text::_('JGLOBAL_TITLE'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_GROUP_MEMBERS'); ?></th><th>ID</th></tr></thead><tbody>
<?php foreach($this->items as $i=>$item): ?><tr><td><?php echo HTMLHelper::_('grid.id',$i,$item->id); ?></td><td><?php echo HTMLHelper::_('jgrid.published',$item->state,$i,'productgroups.',$this->getCurrentUser()->authorise('core.edit.state','com_dharmashop')); ?></td><td><a href="<?php echo Route::_('index.php?option=com_dharmashop&task=productgroup.edit&id='.(int)$item->id); ?>"><?php echo $this->escape($item->title); ?></a></td><td><?php echo (int)$item->members_count; ?></td><td><?php echo (int)$item->id; ?></td></tr><?php endforeach; ?>
</tbody></table><?php echo $this->pagination->getListFooter(); ?>
<input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><?php echo HTMLHelper::_('form.token'); ?></form>
