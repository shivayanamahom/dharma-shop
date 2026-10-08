<?php
declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Shipping\TypeRegistry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=shipping&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="shipping-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>
    <div class="row">
        <div class="col-lg-9">
            <div class="main-card">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_SHIPPING_COMMON'); ?></legend>
                    <?php echo $this->form->renderField('description'); ?>
                </fieldset>
                <?php foreach (TypeRegistry::current()->all() as $type) : ?>
                    <?php // Настройки типа показываются по выбранному типу (showon поля plugin). ?>
                    <div><?php echo $this->form->renderFieldset('type_' . $type->id()); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-3">
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_SHIPPING_TYPE'); ?></legend>
                <?php echo $this->form->renderFieldset('identity'); ?>
            </fieldset>
            <fieldset class="options-form form-vertical">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                <?php echo $this->form->renderFieldset('publishing'); ?>
            </fieldset>
        </div>
    </div>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
