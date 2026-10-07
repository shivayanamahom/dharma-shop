<?php
/** Блок списка товаров и ссылок порций: общий для страницы и AJAX-фрагмента. */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
    <?php if (!$this->items) : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_NO_PRODUCTS'); ?></p>
    <?php elseif ($this->productView === 'table') : ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_PRODUCTS'); ?></caption>
                <tbody><?php foreach ($this->items as $product) : ?><?php echo $this->renderProduct($product); ?><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="<?php echo $this->escape($this->productView === 'list' ? 'row row-cols-1 g-3' : $this->gridClasses); ?>">
            <?php foreach ($this->items as $product) : ?><div class="col"><?php echo $this->renderProduct($product); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($this->after || $this->next) : ?>
        <nav class="d-flex justify-content-between gap-3 mt-4" aria-label="<?php echo Text::_('COM_DHARMASHOP_PRODUCT_PAGES'); ?>">
            <div><?php if ($this->after) : ?><a class="btn btn-outline-secondary" href="<?php echo $this->escape($this->listingRoute()); ?>" data-dharmashop-after="0"><?php echo Text::_('COM_DHARMASHOP_FIRST_PAGE'); ?></a><?php endif; ?></div>
            <?php if ($this->next) : ?><a class="btn btn-outline-primary" rel="next" href="<?php echo $this->escape($this->listingRoute($this->next)); ?>" data-dharmashop-after="<?php echo (int) $this->next; ?>"><?php echo Text::_('COM_DHARMASHOP_NEXT_PAGE'); ?></a><?php endif; ?>
        </nav>
    <?php endif; ?>
