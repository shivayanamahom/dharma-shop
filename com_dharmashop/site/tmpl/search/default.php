<?php
/**
 * Результаты поиска товаров: строка поиска, сортировка и вид, список карточек (`default_items.php`, как в каталоге), страницы.
 * Форма — обычный GET без JavaScript. Данные готовит вид (`$this->items`, `$this->query`, `$this->total`), SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Dharma\Component\DharmaShop\Site\Support\CatalogModules;

$slots = CatalogModules::render();

$modes = ['grid' => 'COM_DHARMASHOP_VIEW_GRID', 'list' => 'COM_DHARMASHOP_VIEW_LIST', 'table' => 'COM_DHARMASHOP_VIEW_TABLE'];
?>
<div id="dharma_shop" class="dharmaSearch">
<section class="py-4">
    <?php if ($this->showHeading) : ?>
        <h1 class="mb-4"><?php echo Text::_('COM_DHARMASHOP_SEARCH'); ?></h1>
    <?php endif; ?>
    <?php if ($slots['top'] !== '') : ?><div class="mb-4 dharmaCatalogTop" data-dharmashop-module-position="dharmashop-products-top"><?php echo $slots['top']; ?></div><?php endif; ?>
    <div class="row g-4 dharmaCatalogColumns">
        <?php if ($slots['left'] !== '') : ?><aside class="col-12 col-lg-3 dharmaCatalogLeft" data-dharmashop-module-position="dharmashop-products-left"><?php echo $slots['left']; ?></aside><?php endif; ?>
        <div class="col-12 col-lg-<?php echo 12 - ($slots['left'] !== '' ? 3 : 0) - ($slots['right'] !== '' ? 3 : 0); ?> dharmaCatalogMain">
    <form action="<?php echo $this->escape($this->searchUrl); ?>" method="get" class="mb-4 dharmaSearchForm" role="search" data-dharmashop-listing-controls>
        <?php echo $this->filterHiddenInputs(); ?>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg">
                <label for="dharmashop-search-q" class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_SEARCH_LABEL'); ?></label>
                <input type="search" id="dharmashop-search-q" name="filter[search]" class="form-control" value="<?php echo $this->escape($this->query); ?>" maxlength="100" placeholder="<?php echo $this->escape(Text::_('COM_DHARMASHOP_SEARCH_PLACEHOLDER')); ?>" autocomplete="off">
            </div>
            <div class="col-6 col-lg-auto">
                <label for="dharmashop-search-sort" class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_SORT_LABEL'); ?></label>
                <select id="dharmashop-search-sort" name="sort" class="form-select">
                    <?php foreach ($this->sortOptions as $value => $label) : ?>
                        <option value="<?php echo $this->escape($value); ?>"<?php echo $this->ordering === $value ? ' selected' : ''; ?>><?php echo $this->escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-auto">
                <select name="product_view" class="form-select" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_VIEW_LABEL')); ?>">
                    <?php foreach ($modes as $value => $label) : ?>
                        <option value="<?php echo $value; ?>"<?php echo $this->productView === $value ? ' selected' : ''; ?>><?php echo $this->escape(Text::_($label)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-auto">
                <button type="submit" class="btn btn-outline-primary w-100"><?php echo Text::_('COM_DHARMASHOP_SEARCH_BUTTON'); ?></button>
            </div>
        </div>
    </form>
    <?php if ($this->query === '') : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_SEARCH_ENTER'); ?></p>
    <?php else : ?>
        <?php echo $this->loadTemplate('items'); ?>
    <?php endif; ?>
        </div>
        <?php if ($slots['right'] !== '') : ?><aside class="col-12 col-lg-3 dharmaCatalogRight" data-dharmashop-module-position="dharmashop-products-right"><?php echo $slots['right']; ?></aside><?php endif; ?>
    </div>
</section>
</div>
