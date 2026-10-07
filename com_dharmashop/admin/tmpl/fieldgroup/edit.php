<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Fieldgroup\HtmlView $this */
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_dharmashop&view=fieldgroup&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="fieldgroup-form" class="form-validate" aria-label="<?php echo Text::_('COM_DHARMASHOP_FIELDGROUP_FORM_LABEL', true); ?>">
    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>
    <div class="row">
        <div class="col-lg-9">
            <div class="main-card p-4">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_DHARMASHOP_FIELDGROUP_ASSIGNMENT'); ?></legend>
                    <?php echo $this->form->renderField('target'); ?>
                    <?php echo $this->form->renderField('tab_mode'); ?>
                </fieldset>
                <p class="text-muted mb-0"><?php echo Text::_('COM_DHARMASHOP_FIELDGROUP_PURPOSE'); ?></p>
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
