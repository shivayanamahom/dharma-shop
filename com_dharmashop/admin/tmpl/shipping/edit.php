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
                    <?php echo $this->form->renderField('image'); ?>
                    <?php echo $this->form->renderField('description'); ?>
                </fieldset>
                <?php // Настройки типа: каждый fieldset `type_<id>` и `type_<id>_*` формы плагина — отдельный блок; блок виден при выбранном типе (showon поля plugin). ?>
                <?php foreach (TypeRegistry::current()->all() as $type) : ?>
                    <?php foreach ($this->form->getFieldsets() as $setName => $fieldset) : ?>
                        <?php if ($setName !== 'type_' . $type->id() && !str_starts_with($setName, 'type_' . $type->id() . '_')) { continue; } ?>
                        <div data-showon='[{"field":"jform[plugin]","values":["<?php echo $this->escape($type->id()); ?>"],"sign":"=","op":""}]'>
                            <fieldset class="options-form">
                                <?php if (!empty($fieldset->label)) : ?><legend><?php echo Text::_($fieldset->label); ?></legend><?php endif; ?>
                                <?php echo $this->form->renderFieldset($setName); ?>
                            </fieldset>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
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
                <?php // Вкладка «Настройка карты» — только если тип описывает fieldset `map_<id>` (например, самовывоз). ?>
                <?php
                $mapSets = [];
                foreach (TypeRegistry::current()->all() as $type) {
                    $fieldsets = $this->form->getFieldsets();
                    if (isset($fieldsets['map_' . $type->id()])) {
                        $mapSets[$type->id()] = $fieldsets['map_' . $type->id()];
                    }
                }
                ?>
                <?php if ($mapSets) : ?>
                    <?php echo HTMLHelper::_('uitab.addTab', 'shippingTab', 'map', Text::_('COM_DHARMASHOP_SHIPPING_TAB_MAP')); ?>
                    <?php foreach ($mapSets as $typeId => $fieldset) : ?>
                        <div data-showon='[{"field":"jform[plugin]","values":["<?php echo $this->escape($typeId); ?>"],"sign":"=","op":""}]'>
                            <fieldset class="options-form">
                                <legend><?php echo Text::_($fieldset->label ?: 'COM_DHARMASHOP_SHIPPING_TAB_MAP'); ?></legend>
                                <?php echo $this->form->renderFieldset('map_' . $typeId); ?>
                            </fieldset>
                        </div>
                    <?php endforeach; ?>
                    <div class="alert alert-info" data-showon='[{"field":"jform[plugin]","values":<?php echo $this->escape(json_encode(array_map('strval', array_keys($mapSets)))); ?>,"sign":"!=","op":""}]'><?php echo Text::_('COM_DHARMASHOP_SHIPPING_MAP_NONE_FOR_TYPE'); ?></div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php endif; ?>
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
