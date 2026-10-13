<?php

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop_landing&view=page&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="page-form" class="form-validate">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>
    <div class="row">
        <div class="col-lg-9">
            <div class="main-card">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'pageTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'pageTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_LANDING_SELECTION'); ?></legend>
                    <?php foreach (['catid', 'source', 'products'] as $name) { echo $this->form->renderField($name); } ?>
                </fieldset>
                <fieldset class="options-form" id="landing-conditions">
                    <legend><?php echo Text::_('COM_DHARMASHOP_LANDING_CONDITIONS'); ?></legend>
                    <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_LANDING_CONDITIONS_DESC'); ?></p>
                    <?php echo $this->form->renderFieldset('conditions'); ?>
                </fieldset>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_LANDING_DISPLAY'); ?></legend>
                    <?php foreach (['sort', 'product_view'] as $name) { echo $this->form->renderField($name); } ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'pageTab', 'description', Text::_('COM_DHARMASHOP_LANDING_TAB_DESCRIPTION')); ?>
                <fieldset class="options-form">
                    <?php foreach (['image', 'image_alt', 'introtext', 'fulltext'] as $name) { echo $this->form->renderField($name); } ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'pageTab', 'seo', Text::_('COM_DHARMASHOP_LANDING_TAB_SEO')); ?>
                <fieldset class="options-form">
                    <?php foreach (['meta_title', 'meta_description', 'robots'] as $name) { echo $this->form->renderField($name); } ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>
        <div class="col-lg-3">
            <fieldset class="options-form form-vertical">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                <?php echo $this->form->renderFieldset('publishing'); ?>
            </fieldset>
        </div>
    </div>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
