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

use Joomla\CMS\Language\Text;

/**
 * Окно «Пакетная обработка»: блоки-карточки строятся из XML-формы вида (batchForm) — заголовок и пояснение из fieldset,
 * поля значения и «Действие» из его полей. Чтобы добавить блок, достаточно описать его в форме и в модели.
 *
 * @var \Joomla\CMS\MVC\View\HtmlView $this
 */
?>
<div class="p-3" data-dharmashop-batch-container>
    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_BATCH_HINT_CATEGORIES'); ?></p>
    <div class="row">
        <?php foreach ($this->batchForm->getFieldsets() as $name => $fieldset) : ?>
            <?php if (!$this->batchForm->getFieldset($name)) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="col-lg-6 mb-3">
                <fieldset class="options-form h-100">
                    <legend><?php echo Text::_($fieldset->label); ?></legend>
                    <?php if (!empty($fieldset->description)) : ?>
                        <p class="small text-body-secondary"><?php echo Text::_($fieldset->description); ?></p>
                    <?php endif; ?>
                    <div class="form-grid">
                        <?php echo $this->batchForm->renderFieldset($name); ?>
                    </div>
                </fieldset>
            </div>
        <?php endforeach; ?>
    </div>
<div class="btn-toolbar p-3">
    <button type="button" class="btn btn-success ms-auto" data-dharmashop-batch-task="category.batch"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
</div>
</div>
