<?php
/**
 * Блок покупки на странице товара (часть страницы, `loadTemplate('buy')`): наличие, артикул и действия покупки — кнопка «В корзину»,
 * поле количества или виджет «убрать / число / +» (настройка «Режим кнопки на странице товара»). Лежит рядом с default.php, поэтому
 * виден в админке («Шаблоны → Переопределения») и правится независимо. Данные — `$this->buy` (готовит вид); в режиме каталога
 * выводятся только наличие и артикул.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$inStock = (int) $this->item->in_stock !== 0;
?>
<div class="mb-4 dharmaProductBuy">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-body-secondary">
        <span class="<?php echo $inStock ? 'text-success' : ''; ?>"><?php echo Text::_($inStock ? 'COM_DHARMASHOP_PRODUCT_IN_STOCK' : 'COM_DHARMASHOP_PRODUCT_OUT_OF_STOCK'); ?></span>
        <?php if ($this->item->sku !== '') : ?><span><?php echo Text::_('COM_DHARMASHOP_PRODUCT_SKU'); ?>: <?php echo $this->escape($this->item->sku); ?></span><?php endif; ?>
    </div>
    <div class="dharmaProductBuyActions">
        <?php echo (new FileLayout('components.dharmashop.product-card-parts.actions', null, ['component' => 'none', 'client' => 0]))->render($this->buy); ?>
    </div>
    <?php if ($this->stepperTemplate !== '') : ?><template data-dharmashop-stepper-template><?php echo $this->stepperTemplate; ?></template><?php endif; ?>
</div>
