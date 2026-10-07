<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderPanel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Root\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$placeholdersButton = PlaceholderPanel::button();
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=root&layout=edit&extension=' . $this->extension); ?>" method="post" name="adminForm" id="root-form" aria-label="<?php echo Text::_('COM_DHARMASHOP_ROOT_FORM_LABEL', true); ?>" class="form-validate">
    <div class="row">
        <div class="col-lg-9">
            <div class="main-card">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'rootTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

                <?php echo HTMLHelper::_('uitab.addTab', 'rootTab', 'details', Text::_('COM_DHARMASHOP_TAB_MAIN')); ?>
                <?php echo $this->form->renderField('title'); ?>
                <?php echo $this->form->renderField('image', 'params'); ?>
                <?php echo $this->form->renderField('image_alt', 'params'); ?>
                <?php echo $this->form->renderField('introtext', 'params'); ?>
                <?php echo $this->form->renderField('description'); ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <?php echo HTMLHelper::_('uitab.addTab', 'rootTab', 'seo', Text::_('COM_DHARMASHOP_TAB_SEO')); ?>
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_TAB_SEO'); ?></legend>
                    <?php echo $this->form->renderFieldset('seo'); ?>
                </fieldset>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>
        <div class="col-lg-3">
            <fieldset class="options-form form-vertical mb-3">
                <legend><?php echo Text::_('COM_DHARMASHOP_ROOT_INFO'); ?></legend>
                <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_ROOT_INFO_' . strtoupper($this->kind)); ?></p>
                <?php echo $placeholdersButton; ?>
            </fieldset>
        </div>
    </div>

    <?php echo PlaceholderPanel::dialog($this->placeholders); ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="extension" value="<?php echo $this->escape($this->extension); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
