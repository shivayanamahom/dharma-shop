<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.admin-quick-create');
?>
<div class="container-fluid py-4" data-quick-create-complete data-created-id="<?php echo (int) $this->item->id; ?>" data-created-kind="field">
    <div class="alert alert-success mb-0" role="status">
        <span class="icon-check-circle" aria-hidden="true"></span>
        <?php echo Text::sprintf('COM_DHARMASHOP_QUICK_CREATE_SAVED', $this->escape($this->item->title)); ?>
    </div>
</div>
