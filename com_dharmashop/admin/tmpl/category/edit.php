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

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
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
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&layout=edit&id=' . (int) $this->item->id . '&extension=' . $this->extension); ?>" method="post" name="adminForm" id="category-form" aria-label="<?php echo Text::_('COM_DHARMASHOP_CATEGORY_FORM_LABEL', true); ?>" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'categoryTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
        <div class="row">
            <div class="col-lg-9">
                <div class="row">
                    <div class="col-md-6">
                        <?php echo $this->form->renderField('image', 'params'); ?>
                        <?php echo $this->form->renderField('image_alt', 'params'); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo $this->form->renderField('icon', 'params'); ?>
                    </div>
                </div>
                <?php echo $this->form->renderField('introtext', 'params'); ?>
                <?php echo $this->form->renderField('description'); ?>
            </div>
            <div class="col-lg-3">
                <div class="alert alert-secondary py-2">
                    <?php echo Text::_('COM_DHARMASHOP_CAT_TYPE'); ?>: <strong><?php echo Text::_($typeLabel); ?></strong>
                </div>
                <?php
                $this->fields = $isBadge ? ['published', 'access', 'note'] : ['parent_id', 'published', 'access', 'note'];
                echo LayoutHelper::render('joomla.edit.global', $this);
                $this->fields = null;
                ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if (!$isBadge) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'display', Text::_('COM_DHARMASHOP_CAT_FIELDSET_DISPLAY')); ?>
            <?php echo $this->form->renderFieldset('dharmashop_display'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php foreach ($extraFieldsets as $name) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', $name, Text::_($isBadge ? 'COM_DHARMASHOP_BADGE_FIELDSET_LABEL' : 'COM_DHARMASHOP_BRAND_FIELDSET_LABEL')); ?>
            <?php echo $this->form->renderFieldset($name); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'metadata', Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?></legend>
            <?php echo $this->form->renderFieldset('metadata'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if ($canAdmin) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'permissions', Text::_('JGLOBAL_ACTION_PERMISSIONS_LABEL')); ?>
            <?php echo $this->form->getInput('rules'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <?php echo $this->form->getInput('extension'); ?>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
