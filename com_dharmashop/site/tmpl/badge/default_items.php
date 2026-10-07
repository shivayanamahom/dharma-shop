<?php
/** Блок списка товаров и постраничной навигации: общий для страницы и AJAX-фрагмента. */
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
    <?php if ($this->pagination) : ?>
        <?php echo $this->pagination->getPaginationLinks(); ?>
    <?php endif; ?>
