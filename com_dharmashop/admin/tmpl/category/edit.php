<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderPanel;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Category\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');

$canAdmin = $this->getCurrentUser()->authorise('core.admin', 'com_dharmashop');
$isBadge = $this->extension === CategoryTypes::BADGE;
$typeLabel = CategoryTypes::all()[$this->extension];
$extraFieldsets = array_filter(['dharmashop_brand', 'dharmashop_badge'], fn (string $name) => (bool) $this->form->getFieldset($name));

$placeholdersButton = PlaceholderPanel::button();
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&layout=edit&id=' . (int) $this->item->id . '&extension=' . $this->extension); ?>" method="post" name="adminForm" id="category-form" aria-label="<?php echo Text::_('COM_DHARMASHOP_CATEGORY_FORM_LABEL', true); ?>" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <?php echo PlaceholderPanel::dialog($this->placeholders); ?>

    <div class="row">
        <div class="col-lg-9">
        <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'categoryTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
            <?php echo $this->form->renderField('image', 'params'); ?>
            <?php echo $this->form->renderField('image_alt', 'params'); ?>
            <?php echo $this->form->renderField('icon', 'params'); ?>
            <?php echo $this->form->renderField('introtext', 'params'); ?>
            <?php echo $this->form->renderField('description'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if (!$isBadge) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'display', Text::_('COM_DHARMASHOP_CAT_FIELDSET_DISPLAY')); ?>
            <?php echo $this->form->renderFieldset('dharmashop_display'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php if ($this->extension === CategoryTypes::CATEGORY) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'order', Text::_('COM_DHARMASHOP_TAB_ORDER')); ?>
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_DHARMASHOP_ORDER_BLOCK_QTY'); ?></legend>
                <?php echo $this->form->renderFieldset('dharmashop_order_qty'); ?>
            </fieldset>
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_DHARMASHOP_ORDER_BLOCK_STOCK'); ?></legend>
                <?php echo $this->form->renderFieldset('dharmashop_order_stock'); ?>
            </fieldset>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php foreach ($extraFieldsets as $name) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', $name, Text::_($isBadge ? 'COM_DHARMASHOP_BADGE_FIELDSET_LABEL' : 'COM_DHARMASHOP_BRAND_FIELDSET_LABEL')); ?>
            <?php echo $this->form->renderFieldset($name); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'seo', Text::_('COM_DHARMASHOP_TAB_SEO')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_DHARMASHOP_TAB_SEO'); ?></legend>
            <?php echo $this->form->renderFieldset('seo'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if ($canAdmin) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'permissions', Text::_('JGLOBAL_ACTION_PERMISSIONS_LABEL')); ?>
            <?php echo $this->form->getInput('rules'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        </div>
        </div>
        <div class="col-lg-3">
                <?php
                // Боковая колонка: родитель и состояние, затем карточка со сведениями, затем доступ и заметка.
                $this->fields = $isBadge ? [['published', 'state', 'enabled']] : ['parent_id', ['published', 'state', 'enabled']];
                echo LayoutHelper::render('joomla.edit.global', $this);
                $this->fields = ['access', 'note'];
                ob_start();
                echo LayoutHelper::render('joomla.edit.global', $this);
                $afterInfo = ob_get_clean();
                $this->fields = null;
                ?>
                <fieldset class="options-form form-vertical mb-3">
                        <div class="control-group">
                            <div class="control-label"><label for="cat-type"><?php echo Text::_('COM_DHARMASHOP_CAT_TYPE'); ?></label></div>
                            <div class="controls"><input type="text" id="cat-type" class="form-control" value="<?php echo $this->escape(Text::_($typeLabel)); ?>" readonly></div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="cat-id"><?php echo 'ID'; ?></label></div>
                            <div class="controls"><input type="text" id="cat-id" class="form-control" value="<?php echo $this->item->id ? (int) $this->item->id : '—'; ?>" readonly></div>
                        </div>
                </fieldset>
                <fieldset class="options-form form-vertical mb-3">
                    <legend><?php echo Text::_('COM_DHARMASHOP_CAT_COUNTS'); ?></legend>
                        <div class="control-group">
                            <div class="control-label"><label for="cat-cnt-total"><?php echo Text::_('COM_DHARMASHOP_CAT_COUNT_TOTAL_LABEL'); ?></label></div>
                            <div class="controls"><input type="text" id="cat-cnt-total" class="form-control" value="<?php echo (int) $this->counts['total']; ?>" readonly></div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="cat-cnt-published"><?php echo Text::_('COM_DHARMASHOP_CAT_COUNT_PUBLISHED_LABEL'); ?></label></div>
                            <div class="controls"><input type="text" id="cat-cnt-published" class="form-control" value="<?php echo (int) $this->counts['published']; ?>" readonly></div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="cat-cnt-stock"><?php echo Text::_('COM_DHARMASHOP_CAT_COUNT_IN_STOCK_LABEL'); ?></label></div>
                            <div class="controls"><input type="text" id="cat-cnt-stock" class="form-control" value="<?php echo (int) $this->counts['in_stock']; ?>" readonly></div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="cat-price-range"><?php echo Text::_('COM_DHARMASHOP_CAT_PRICE_RANGE_LABEL'); ?></label></div>
                            <div class="controls"><input type="text" id="cat-price-range" class="form-control" value="<?php echo $this->escape($this->priceRange); ?>" readonly></div>
                        </div>
                    <?php if ($this->item->id && $this->counts['total'] > 0) : ?>
                        <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_dharmashop&view=products&filter[category_id]=' . (int) $this->item->id . '&filter[published]=*'); ?>"><?php echo Text::_('COM_DHARMASHOP_CAT_SHOW_PRODUCTS'); ?></a>
                    <?php endif; ?>
                    <?php echo $placeholdersButton; ?>
                </fieldset>
                <?php echo $afterInfo; ?>
        </div>
    </div>

    <?php echo $this->form->getInput('extension'); ?>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
