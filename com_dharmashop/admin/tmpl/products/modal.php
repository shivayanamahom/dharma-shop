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

use Dharma\Component\DharmaShop\Administrator\Catalog\ProductPicker;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Products\HtmlView $this */

// Окно выбора товара (поле modal_product, например в пункте меню): клик по названию передаёт номер и название в родительское окно.
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')->useScript('multiselect')->useScript('modal-content-select');

$state = $this->getModel()->getState();
$listOrder = $this->escape((string) $state->get('list.ordering'));
$listDirn = $this->escape((string) $state->get('list.direction'));
$function = Factory::getApplication()->getInput()->getCmd('function', 'jSelectProduct');
$excluded = implode(',', (array) $state->get('picker.exclude', []));
if ($excluded !== '') { $this->pagination->setAdditionalUrlParam('picker_exclude', $excluded); }
$members = (bool) $state->get('picker.members');
$groupId = (int) $state->get('picker.group');
if ($members) {
    $this->pagination->setAdditionalUrlParam('picker_members', 1);
    $this->pagination->setAdditionalUrlParam('picker_group', $groupId);
}
$stateIcons = [-2 => 'icon-trash', 0 => 'icon-unpublish', 1 => 'icon-publish', 2 => 'icon-archive'];
$picker = new ProductPicker(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_dharmashop&view=products&layout=modal&tmpl=component&function=' . $function . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-sm">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_PRODUCTS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="w-1 text-center"><?php echo Text::_('COM_DHARMASHOP_HEADING_IMAGE'); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="text-end"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DHARMASHOP_HEADING_PRICE', 'a.price_min', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="w-1 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) :
                        $preview = $picker->presentation($item, 'admin.picker.modal');
                        $selectedPreview = $picker->presentation($item, $members ? 'admin.picker.member' : 'admin.picker.field');
                        ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center"><span class="tbody-icon"><span class="<?php echo $stateIcons[(int) $item->state] ?? 'icon-unpublish'; ?>" aria-hidden="true"></span></span></td>
                            <td class="text-center align-middle">
                                <?php if ($preview['image'] !== '') : ?>
                                    <img src="<?php echo $this->escape($preview['image']); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" class="rounded object-fit-cover">
                                <?php else : ?><span class="icon-image text-body-secondary" aria-hidden="true"></span><?php endif; ?>
                            </td>
                            <th scope="row">
                                <a class="select-link" href="javascript:void(0)" data-content-select data-content-type="com_dharmashop.product"
                                    data-function="<?php echo $this->escape($function); ?>" data-id="<?php echo (int) $item->id; ?>"
                                    data-picker-product="<?php echo $this->escape(json_encode($selectedPreview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                                    data-title="<?php echo $this->escape($item->title); ?>"><?php echo $this->escape($item->title); ?></a>
                                <div class="small break-word"><?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?></div>
                                <div class="small">
                                    <?php echo Text::_('JCATEGORY') . ': ' . $this->escape((string) $item->category_title); ?>
                                    <?php if ((string) $item->sku !== '') : ?> · <?php echo Text::_('COM_DHARMASHOP_SKU'); ?>: <?php echo $this->escape($item->sku); ?><?php endif; ?>
                                </div>
                            </th>
                            <td class="text-end align-middle">
                                <div class="fw-bold"><?php echo $this->escape($preview['price']); ?></div>
                                <?php if ($preview['regular'] !== '') : ?>
                                    <div class="small text-nowrap"><del class="text-body-secondary"><?php echo $this->escape($preview['regular']); ?></del> <span class="badge bg-danger"><?php echo $this->escape($preview['discount']); ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="small d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <input type="hidden" name="picker_exclude" value="<?php echo $this->escape($excluded); ?>">
        <?php if ($members) : ?>
            <input type="hidden" name="picker_members" value="1">
            <input type="hidden" name="picker_group" value="<?php echo $groupId; ?>">
        <?php endif; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
