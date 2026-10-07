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
                <div class="card mb-3">
                    <div class="card-header py-2 fw-bold"><?php echo Text::_('COM_DHARMASHOP_CAT_INFO'); ?></div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CAT_TYPE'); ?></span>
                            <strong><?php echo Text::_($typeLabel); ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-body-secondary">ID</span>
                            <span class="badge bg-secondary"><?php echo $this->item->id ? (int) $this->item->id : '—'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_CAT_HEADING_PRODUCTS'); ?></span>
                            <span class="text-end">
                                <?php if ($this->item->id && $this->counts['total'] > 0) : ?>
                                    <a class="btn btn-sm <?php echo $this->counts['published'] > 0 ? 'btn-success' : 'btn-secondary'; ?>" href="<?php echo Route::_('index.php?option=com_dharmashop&view=products&filter[category_id]=' . (int) $this->item->id . '&filter[published]=*'); ?>" title="<?php echo Text::_('COM_DHARMASHOP_CAT_SHOW_PRODUCTS', true); ?>"><?php echo (int) $this->counts['total']; ?></a>
                                    <span class="d-block text-body-secondary"><?php echo Text::sprintf('COM_DHARMASHOP_CAT_PRODUCTS_PUBLISHED', (int) $this->counts['published']); ?></span>
                                <?php else : ?>
                                    <span class="btn btn-sm btn-secondary disabled">0</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    </ul>
                </div>
                <?php echo $afterInfo; ?>
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
