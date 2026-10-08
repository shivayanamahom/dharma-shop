<?php
declare(strict_types=1);
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
\defined('_JEXEC') or die;
$phase = $this->job['phase'] ?? '';
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=imagecommands&operation='.$this->operation); ?>" method="post" name="adminForm" id="adminForm">
    <div class="card">
        <div class="card-body">
            <p><?php echo Text::_($this->operation==='prepare' ? 'COM_DHARMASHOP_IMAGE_COMMAND_PREPARE_DESC' : 'COM_DHARMASHOP_IMAGE_COMMAND_CLEAR_DESC'); ?></p>
            <?php if ($this->operation==='prepare' && !$this->job) : ?>
                <?php echo $this->form->renderField('context'); ?>
            <?php endif; ?>
            <?php if ($this->job) : ?>
                <div class="alert alert-<?php echo $phase==='done' ? 'success' : ($phase==='review' ? 'warning' : 'info'); ?>" role="status">
                    <?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_PHASE_'.strtoupper($phase)); ?>
                </div>
                <dl class="row mb-0">
                <?php if ($this->operation==='prepare') : ?>
                    <dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_SELECT'); ?></dt>
                    <dd class="col-sm-8"><?php echo $this->escape($this->contextLabel); ?></dd>
                    <?php foreach (['records','processed','ready','skipped','failed'] as $counter) : ?>
                        <dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_'.strtoupper($counter)); ?></dt>
                        <dd class="col-sm-8"><?php echo (int)$this->job[$counter]; ?></dd>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php foreach (['records','files','deleted','failed'] as $counter) : ?>
                        <dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_'.strtoupper($counter)); ?></dt>
                        <dd class="col-sm-8"><?php echo (int)$this->job[$counter]; ?></dd>
                    <?php endforeach; ?>
                    <dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_BYTES'); ?></dt>
                    <dd class="col-sm-8"><?php echo number_format((int)$this->job['bytes']/1048576,2); ?> MiB</dd>
                    <dt class="col-sm-4"><?php echo Text::_('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_PROTECTED'); ?></dt>
                    <dd class="col-sm-8"><?php echo count($this->job['protected']); ?></dd>
                <?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>
    </div>
    <input type="hidden" name="operation" value="<?php echo $this->operation; ?>">
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
