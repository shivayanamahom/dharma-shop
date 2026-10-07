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

        <?php echo LayoutHelper::render('field-tabs', [
            'form' => $this->form,
            'groups' => $this->fieldGroups,
            'tabSet' => 'categoryTab',
        ], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'display', Text::_('COM_DHARMASHOP_CAT_FIELDSET_DISPLAY')); ?>
            <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_DISPLAY_ENTITY_INHERIT_DESC'); ?></p>
            <?php if ($this->extension !== CategoryTypes::CATEGORY) : ?>
                <div class="alert alert-info"><?php echo Text::_('COM_DHARMASHOP_DISPLAY_ENTITY_PENDING_DESC'); ?></div>
            <?php endif; ?>
            <?php foreach (['dharmashop_display', 'dharmashop_display_children', 'dharmashop_display_brands', 'dharmashop_display_products'] as $name) : ?>
                <?php if ($this->form->getFieldset($name)) : ?>
                    <fieldset class="options-form">
                        <legend><?php echo Text::_($this->form->getFieldsets('params')[$name]->label); ?></legend>
                        <?php echo $this->form->renderFieldset($name); ?>
                    </fieldset>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

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
            <?php // Боковая колонка — рамки (fieldset), как у товара: основное, шорткоды, сведения, количество, доступ. ?>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_MAIN'); ?></legend>
                <?php if (!$isBadge) : ?>
                    <?php echo $this->form->renderField('parent_id'); ?>
                <?php endif; ?>
                <?php echo $this->form->renderField('published'); ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_PH_TITLE'); ?></legend>
                <p class="text-muted small mb-2"><?php echo Text::_('COM_DHARMASHOP_PH_SIDEBAR_HINT'); ?></p>
                <?php echo $placeholdersButton; ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_SIDEBAR_INFO'); ?></legend>
                <div class="control-group">
                    <div class="control-label"><label for="cat-type"><?php echo Text::_('COM_DHARMASHOP_CAT_TYPE'); ?></label></div>
                    <div class="controls"><input type="text" id="cat-type" class="form-control" value="<?php echo $this->escape(Text::_($typeLabel)); ?>" readonly></div>
                </div>
                <div class="control-group">
                    <div class="control-label"><label for="cat-id">ID</label></div>
                    <div class="controls"><input type="text" id="cat-id" class="form-control" value="<?php echo $this->item->id ? (int) $this->item->id : '—'; ?>" readonly></div>
                </div>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_CAT_COUNTS'); ?></legend>
                <?php foreach ([
                    ['cat-cnt-total', 'COM_DHARMASHOP_CAT_COUNT_TOTAL_LABEL', (string) (int) $this->counts['total']],
                    ['cat-cnt-published', 'COM_DHARMASHOP_CAT_COUNT_PUBLISHED_LABEL', (string) (int) $this->counts['published']],
                    ['cat-cnt-stock', 'COM_DHARMASHOP_CAT_COUNT_IN_STOCK_LABEL', (string) (int) $this->counts['in_stock']],
                    ['cat-price-range', 'COM_DHARMASHOP_CAT_PRICE_RANGE_LABEL', $this->priceRange],
                ] as [$id, $label, $value]) : ?>
                    <div class="control-group">
                        <div class="control-label"><label for="<?php echo $id; ?>"><?php echo Text::_($label); ?></label></div>
                        <div class="controls"><input type="text" id="<?php echo $id; ?>" class="form-control" value="<?php echo $this->escape($value); ?>" readonly></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($this->item->id && $this->counts['total'] > 0) : ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_dharmashop&view=products&filter[category_id]=' . (int) $this->item->id . '&filter[published]=*'); ?>"><?php echo Text::_('COM_DHARMASHOP_CAT_SHOW_PRODUCTS'); ?></a>
                <?php endif; ?>
            </fieldset>
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('JFIELD_ACCESS_LABEL'); ?></legend>
                <?php foreach (['access', 'note'] as $name) : ?>
                    <?php echo $this->form->renderField($name); ?>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </div>

    <?php echo $this->form->getInput('extension'); ?>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
