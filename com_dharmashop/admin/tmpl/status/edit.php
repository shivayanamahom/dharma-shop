<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=status&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="status-form" class="form-validate">
    <div class="main-card p-4"><fieldset class="options-form"><legend><?php echo Text::_('COM_DHARMASHOP_STATUS_TITLE'); ?></legend><?php echo $this->form->renderFieldset('details'); ?></fieldset></div>
    <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
</form>
