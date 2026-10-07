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

/**
 * Окно «Пакетная обработка» категорий, брендов или бейджей (тип — $this->extension). Заполненные поля применяются ко всем выбранным.
 * Добавить операцию: поле здесь + строка в CategoryModel::$batch_commands + метод batch<Имя>.
 *
 * @var \Dharma\Component\DharmaShop\Administrator\View\Categories\HtmlView $this
 */

$isBadge = $this->extension === CategoryTypes::BADGE;
$notChange = Text::_('COM_DHARMASHOP_BATCH_NOT_CHANGE');
$orderings = ['order' => 'ORDER', 'price_asc' => 'PRICE_ASC', 'price_desc' => 'PRICE_DESC', 'new' => 'NEW', 'title' => 'TITLE', 'global' => 'GLOBAL'];
?>
<div class="p-3">
    <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_BATCH_HINT_CATEGORIES'); ?></p>
    <div class="row">
        <div class="form-group col-md-6 mb-3">
            <?php echo LayoutHelper::render('joomla.html.batch.access', []); ?>
        </div>
        <?php if (!$isBadge) : ?>
            <div class="form-group col-md-6 mb-3">
                <label id="batch-parent_id-lbl" for="batch-parent_id"><?php echo Text::_('COM_DHARMASHOP_BATCH_MOVE_PARENT'); ?></label>
                <select name="batch[parent_id]" class="form-select" id="batch-parent_id" aria-labelledby="batch-parent_id-lbl">
                    <option value=""><?php echo $notChange; ?></option>
                    <option value="1"><?php echo Text::_('JGLOBAL_ROOT_PARENT'); ?></option>
                    <?php echo HTMLHelper::_('select.options', HTMLHelper::_('category.options', $this->extension)); ?>
                </select>
            </div>
            <div class="form-group col-md-6 mb-3">
                <label id="batch-show_in_list-lbl" for="batch-show_in_list"><?php echo Text::_($this->extension === CategoryTypes::BRAND ? 'COM_DHARMASHOP_CAT_SHOW_IN_LIST_BRAND_LABEL' : 'COM_DHARMASHOP_CAT_SHOW_IN_LIST_CATEGORY_LABEL'); ?></label>
                <select name="batch[show_in_list]" class="form-select" id="batch-show_in_list" aria-labelledby="batch-show_in_list-lbl">
                    <option value=""><?php echo $notChange; ?></option>
                    <option value="yes"><?php echo Text::_('COM_DHARMASHOP_SHOW'); ?></option>
                    <option value="no"><?php echo Text::_('COM_DHARMASHOP_HIDE'); ?></option>
                </select>
            </div>
            <div class="form-group col-md-6 mb-3">
                <label id="batch-list_ordering-lbl" for="batch-list_ordering"><?php echo Text::_('COM_DHARMASHOP_CAT_LIST_ORDERING_LABEL'); ?></label>
                <select name="batch[list_ordering]" class="form-select" id="batch-list_ordering" aria-labelledby="batch-list_ordering-lbl">
                    <option value=""><?php echo $notChange; ?></option>
                    <?php foreach ($orderings as $value => $key) : ?>
                        <option value="<?php echo $value; ?>"><?php echo Text::_('COM_DHARMASHOP_CAT_ORDERING_' . $key); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6 mb-3">
                <label id="batch-list_limit-lbl" for="batch-list_limit"><?php echo Text::_('COM_DHARMASHOP_CAT_LIST_LIMIT_LABEL'); ?></label>
                <select name="batch[list_limit]" class="form-select" id="batch-list_limit" aria-labelledby="batch-list_limit-lbl">
                    <option value=""><?php echo $notChange; ?></option>
                    <option value="global"><?php echo Text::_('COM_DHARMASHOP_CAT_ORDERING_GLOBAL'); ?></option>
                    <?php foreach ([12, 24, 36, 48, 72, 96] as $limit) : ?>
                        <option value="<?php echo $limit; ?>"><?php echo $limit; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="btn-toolbar p-3">
    <joomla-toolbar-button task="category.batch" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
    </joomla-toolbar-button>
</div>
