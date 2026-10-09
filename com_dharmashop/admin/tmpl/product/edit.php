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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Product\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');

$canAdmin = $this->getCurrentUser()->authorise('core.admin', 'com_dharmashop');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="product-form" aria-label="<?php echo Text::_('COM_DHARMASHOP_PRODUCT_FORM_LABEL', true); ?>" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>
    <?php echo PlaceholderPanel::dialog($this->placeholders); ?>

    <div class="row">
        <div class="col-lg-9">
        <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'productTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
            <fieldset class="options-form mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_TAB_IMAGES'); ?></legend>
                <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_GALLERY_DESC'); ?></p>
                <?php echo $this->form->getInput('gallery'); ?>
            </fieldset>
            <?php echo $this->form->renderField('introtext'); ?>
            <?php echo $this->form->renderField('fulltext'); ?>
            <?php echo $this->form->renderField('search_text'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo LayoutHelper::render('field-tabs', [
            'form' => $this->form,
            'groups' => $this->fieldGroups,
            'tabSet' => 'productTab',
        ], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>

        <?php echo LayoutHelper::render('extension-tabs', ['form' => $this->form, 'tabSet' => 'productTab'], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'product-group', Text::_('COM_DHARMASHOP_PRODUCT_GROUP')); ?>
        <fieldset class="options-form"><?php echo $this->form->renderFieldset('product_group'); ?></fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'price', Text::_('COM_DHARMASHOP_TAB_PRICE_STOCK')); ?>
        <div class="row">
            <div class="col-lg-6">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDSET_PRICE'); ?></legend>
                    <?php foreach (['sku', 'price', 'price_zero_free', 'discount', 'price_result', 'discount_start', 'discount_end'] as $name) : ?>
                        <?php echo $this->form->renderField($name); ?>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <div class="col-lg-6">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDSET_STOCK'); ?></legend>
                    <?php foreach (['stock_tracked', 'stock_qty', 'backorder'] as $name) : ?>
                        <?php echo $this->form->renderField($name); ?>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDSET_DIMENSIONS'); ?></legend>
                    <?php foreach (['weight', 'weight_unit', 'length', 'width', 'height', 'dimension_unit'] as $name) : ?>
                        <?php echo $this->form->renderField($name); ?>
                    <?php endforeach; ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'order', Text::_('COM_DHARMASHOP_TAB_ORDER')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_DHARMASHOP_ORDER_BLOCK_QTY'); ?></legend>
            <?php echo $this->form->renderFieldset('order_qty'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'display', Text::_('COM_DHARMASHOP_TAB_DISPLAY')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_DHARMASHOP_TAB_DISPLAY'); ?></legend>
            <?php echo $this->form->renderField('product_layout', 'params'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'category-order', Text::_('COM_DHARMASHOP_TAB_CATEGORY_ORDER')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_DHARMASHOP_TAB_CATEGORY_ORDER'); ?></legend>
            <?php echo $this->form->renderField('category_order'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'seo', Text::_('COM_DHARMASHOP_TAB_SEO')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_DHARMASHOP_TAB_SEO'); ?></legend>
            <?php echo $this->form->renderFieldset('seo'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if ($canAdmin) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'permissions', Text::_('JGLOBAL_ACTION_PERMISSIONS_LABEL')); ?>
            <?php echo $this->form->getInput('rules'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        </div>
        </div>
        <div class="col-lg-3">
            <?php // Боковая колонка — рамки (fieldset): категории, шорткоды, основное, публикация (внизу). ?>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_CATEGORIES'); ?></legend>
                <?php foreach (['catid', 'extra_catids', 'brand_id', 'badge_ids'] as $name) : ?>
                    <?php echo $this->form->renderField($name); ?>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_PH_TITLE'); ?></legend>
                <p class="text-muted small mb-2"><?php echo Text::_('COM_DHARMASHOP_PH_SIDEBAR_HINT'); ?></p>
                <?php echo PlaceholderPanel::button(); ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_MAIN'); ?></legend>
                <?php foreach (['id', 'state'] as $name) : ?>
                    <?php echo $this->form->renderField($name); ?>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                <?php foreach (['created', 'created_by', 'modified', 'modified_by'] as $name) : ?>
                    <?php echo $this->form->renderField($name); ?>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
