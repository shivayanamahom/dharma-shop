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

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'productTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
        <div class="row">
            <div class="col-lg-9">
                <?php echo $this->form->renderField('introtext'); ?>
                <?php echo $this->form->renderField('fulltext'); ?>
            </div>
            <div class="col-lg-3">
                <?php
                // Боковой блок: категории, бренд и бейджи вместе с состоянием, в нужном порядке (штатный блок сам выводит catid).
                $this->fields = ['catid', 'extra_catids', 'brand_id', 'badge_ids', ['published', 'state', 'enabled'], 'featured'];
                echo LayoutHelper::render('joomla.edit.global', $this);
                $this->fields = null;
                ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'price', Text::_('COM_DHARMASHOP_TAB_PRICE_STOCK')); ?>
        <div class="row">
            <div class="col-lg-6">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDSET_PRICE'); ?></legend>
                    <?php foreach (['sku', 'price', 'sale_price', 'sale_start', 'sale_end'] as $name) : ?>
                        <?php echo $this->form->renderField($name); ?>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDSET_UNITS'); ?></legend>
                    <?php foreach (['unit', 'qty_min', 'qty_step'] as $name) : ?>
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
                    <?php foreach (['weight', 'length', 'width', 'height'] as $name) : ?>
                        <?php echo $this->form->renderField($name); ?>
                    <?php endforeach; ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
        <div class="row">
            <div class="col-12 col-lg-6">
                <?php echo LayoutHelper::render('joomla.edit.publishingdata', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'metadata', Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?></legend>
            <?php echo LayoutHelper::render('joomla.edit.metadata', $this); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if ($canAdmin) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'productTab', 'permissions', Text::_('JGLOBAL_ACTION_PERMISSIONS_LABEL')); ?>
            <?php echo $this->form->getInput('rules'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
