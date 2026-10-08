<?php
/** Блок списка товаров и постраничной навигации: общий для страницы и AJAX-фрагмента. */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
    <?php if (!$this->items) : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_SEARCH_EMPTY'); ?></p>
    <?php elseif ($this->productView === 'table') : ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle dharmaProductsTable">
                <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_PRODUCTS'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_DHARMASHOP_TABLE_PRODUCT'); ?></th>
                        <?php foreach ($this->fieldColumns as $alias => $title) : ?><th scope="col" class="d-none d-lg-table-cell" data-field="<?php echo $this->escape($alias); ?>"><?php echo $this->escape($title); ?></th><?php endforeach; ?>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_TABLE_PRICE'); ?></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($this->items as $product) : ?><?php echo $this->renderProduct($product); ?><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="<?php echo $this->escape($this->productView === 'list' ? 'row row-cols-1 g-3' : $this->gridClasses); ?>">
            <?php foreach ($this->items as $product) : ?><div class="col"><?php echo $this->renderProduct($product); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($this->pagination) : ?>
        <div class="mt-4" data-dharmashop-pagination><?php echo $this->pagination->getPaginationLinks(); ?></div>
    <?php endif; ?>
    <?php // Шаблон виджета количества (режим «кнопка → виджет»): cart.js создаёт из него виджет после добавления товара. ?>
    <?php if ($this->stepperTemplate !== '') : ?><template data-dharmashop-stepper-template><?php echo $this->stepperTemplate; ?></template><?php endif; ?>
