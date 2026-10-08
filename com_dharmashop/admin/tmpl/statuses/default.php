<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('table.columns');
$state = $this->getModel()->getState(); $dir = (string) $state->get('list.direction'); $order = (string) $state->get('list.ordering');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=statuses'); ?>" method="post" name="adminForm" id="adminForm"><div class="j-main-container">
    <?php echo LayoutHelper::render('joomla.searchtools.default',['view'=>$this]); ?>
    <?php if (!$this->items) : ?><div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
    <?php else : ?><table class="table" id="statusList"><caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_MENU_STATUSES'); ?></caption><thead><tr><th><?php echo HTMLHelper::_('searchtools.sort','JGLOBAL_TITLE','a.title',$dir,$order); ?></th><th><?php echo Text::_('COM_DHARMASHOP_STATUS_COLOR'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_STATUS_DEFAULT'); ?></th><th><?php echo HTMLHelper::_('searchtools.sort','JSTATUS','a.state',$dir,$order); ?></th><th><?php echo HTMLHelper::_('searchtools.sort','JFIELD_ORDERING_LABEL','a.ordering',$dir,$order); ?></th><th><?php echo HTMLHelper::_('searchtools.sort','JGRID_HEADING_ID','a.id',$dir,$order); ?></th></tr></thead><tbody>
        <?php foreach ($this->items as $item) : $color = in_array($item->color,['primary','secondary','success','danger','warning','info','dark'],true) ? $item->color : 'secondary'; ?><tr><th scope="row"><?php if ($this->getCurrentUser()->authorise('core.edit','com_dharmashop')) : ?><a href="<?php echo Route::_('index.php?option=com_dharmashop&task=status.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->title); ?></a><?php else : ?><?php echo $this->escape($item->title); ?><?php endif; ?><div class="small text-body-secondary"><?php echo $this->escape($item->alias); ?></div></th><td><span class="badge bg-<?php echo $color; ?><?php echo in_array($color,['info','warning'],true) ? ' text-dark' : ' text-white'; ?>"><?php echo $this->escape($item->title); ?></span></td><td><?php echo Text::_($item->is_default ? 'JYES' : 'JNO'); ?></td><td><?php echo Text::_($item->state ? 'JPUBLISHED' : 'JUNPUBLISHED'); ?></td><td><?php echo (int) $item->ordering; ?></td><td><?php echo (int) $item->id; ?></td></tr><?php endforeach; ?>
    </tbody></table><?php echo $this->pagination->getListFooter(); ?><?php endif; ?>
    <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
</div></form>
