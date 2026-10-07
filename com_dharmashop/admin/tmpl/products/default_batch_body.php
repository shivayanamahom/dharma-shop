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

/**
 * Окно «Пакетная обработка». Каждое поле — одна операция; заполненные поля применяются ко всем выбранным товарам.
 * Чтобы добавить операцию: поле здесь + строка в ProductModel::$batch_commands + метод batch<Имя> в ProductModel.
 *
 * @var \Dharma\Component\DharmaShop\Administrator\View\Products\HtmlView $this
 */

$operations = [
    ['category_id', 'COM_DHARMASHOP_BATCH_MOVE_CATEGORY', 'com_dharmashop', 'COM_DHARMASHOP_BATCH_NOT_CHANGE', []],
    ['extra_category_id', 'COM_DHARMASHOP_BATCH_ADD_CATEGORY', 'com_dharmashop', 'COM_DHARMASHOP_BATCH_NOT_CHANGE', []],
    ['brand_id', 'COM_DHARMASHOP_BATCH_SET_BRAND', 'com_dharmashop.brand', 'COM_DHARMASHOP_BATCH_NOT_CHANGE', [['-1', 'COM_DHARMASHOP_FIELD_BRAND_NONE']]],
    ['badge_add_id', 'COM_DHARMASHOP_BATCH_ADD_BADGE', 'com_dharmashop.badge', 'COM_DHARMASHOP_BATCH_NOT_CHANGE', []],
    ['badge_remove_id', 'COM_DHARMASHOP_BATCH_REMOVE_BADGE', 'com_dharmashop.badge', 'COM_DHARMASHOP_BATCH_NOT_CHANGE', []],
];
?>
<div class="p-3">
    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_BATCH_HINT'); ?></p>
    <div class="row">
        <?php foreach ($operations as [$name, $label, $extension, $empty, $extraOptions]) : ?>
            <div class="form-group col-md-6 mb-3">
                <label id="batch-<?php echo $name; ?>-lbl" for="batch-<?php echo $name; ?>"><?php echo Text::_($label); ?></label>
                <select name="batch[<?php echo $name; ?>]" class="form-select" id="batch-<?php echo $name; ?>" aria-labelledby="batch-<?php echo $name; ?>-lbl">
                    <option value=""><?php echo Text::_($empty); ?></option>
                    <?php foreach ($extraOptions as [$value, $text]) : ?>
                        <option value="<?php echo $this->escape($value); ?>"><?php echo Text::_($text); ?></option>
                    <?php endforeach; ?>
                    <?php echo HTMLHelper::_('select.options', HTMLHelper::_('category.options', $extension)); ?>
                </select>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="btn-toolbar p-3">
    <joomla-toolbar-button task="product.batch" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
    </joomla-toolbar-button>
</div>
