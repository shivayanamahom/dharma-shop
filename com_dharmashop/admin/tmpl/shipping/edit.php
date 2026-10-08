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
                <?php echo HTMLHelper::_('uitab.startTabSet', 'shippingTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'shippingTab', 'details', Text::_('COM_DHARMASHOP_SHIPPING_COMMON')); ?>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_SHIPPING_SETTINGS'); ?></legend>
                    <?php echo $this->form->renderField('description'); ?>
                    <?php foreach (TypeRegistry::current()->all() as $type) : ?>
                        <?php // Настройки типа показываются по выбранному типу (showon поля plugin). ?>
                        <?php echo $this->form->renderFieldset('type_' . $type->id()); ?>
                    <?php endforeach; ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php // Всё, что связано с ценой и суммой заказа (фиксированная цена, «бесплатно от суммы», пределы суммы), — отдельная вкладка: fieldset `price_<id>` формы типа. ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'shippingTab', 'prices', Text::_('COM_DHARMASHOP_SHIPPING_TAB_PRICES')); ?>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_SHIPPING_TAB_PRICES_LEGEND'); ?></legend>
                    <?php foreach (TypeRegistry::current()->all() as $type) : ?>
                        <?php echo $this->form->renderFieldset('price_' . $type->id()); ?>
                    <?php endforeach; ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
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
