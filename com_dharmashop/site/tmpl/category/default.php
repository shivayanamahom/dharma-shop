<?php
/** Страница списка товаров; разметка товара находится в отдельном JLayout. */
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
use Joomla\CMS\Language\Text;

$hooks = new LayoutHooks('category', 'category', $this->item, $this->params, $this->items, $this->seo);
$modes = ['grid' => 'COM_DHARMASHOP_VIEW_GRID', 'list' => 'COM_DHARMASHOP_VIEW_LIST', 'table' => 'COM_DHARMASHOP_VIEW_TABLE'];
// Значки переключателя вида (Bootstrap Icons, MIT): без шрифтов и своего CSS; название — для скринридеров и подсказки.
$icons = [
    'grid' => 'M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5z',
    'list' => 'M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
    'table' => 'M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm15 2h-4v3h4zm0 4h-4v3h4zm0 4h-4v3h3a1 1 0 0 0 1-1zm-5 3v-3H6v3zm-5 0v-3H1v2a1 1 0 0 0 1 1zm-4-4h4V8H1zm0-4h4V4H1zm5-3v3h4V4zm4 4H6v3h4z',
];
?>
<div id="dharma_shop" class="dharmaCategory">
<section class="py-4">
    <?php echo $hooks->render('beforeTitle'); ?>
    <?php if ($this->showHeading && $this->seo->h1 !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($this->seo->h1); ?></h1>
    <?php endif; ?>
    <?php echo $hooks->render('afterTitle'); ?>
    <?php echo $hooks->render('beforeContent'); ?>
    <?php if ($this->image !== '') : ?>
        <img class="img-fluid rounded mb-4" src="<?php echo $this->escape($this->image); ?>" alt="<?php echo $this->escape((string) ($this->item->params->get('image_alt') ?: $this->item->title)); ?>" decoding="async">
    <?php endif; ?>
    <?php // Полное описание — только на первой странице списка: на остальных оно дублирует её содержимое (SEO); интро остаётся. ?>
    <?php if (trim((string) $this->item->params->get('introtext', '')) !== '') : ?>
        <div class="mb-4 dharmaIntro"><?php echo $this->text((string) $this->item->params->get('introtext')); ?></div>
    <?php endif; ?>
    <?php if ($this->subcategories) : ?>
        <section class="mb-5" aria-labelledby="dharmashop-subcategories-title">
            <h2 id="dharmashop-subcategories-title" class="visually-hidden"><?php echo Text::_($this->item->id ? 'COM_DHARMASHOP_SUBCATEGORIES' : 'COM_DHARMASHOP_CATEGORIES'); ?></h2>
            <div class="<?php echo $this->escape($this->subcategoryGridClasses); ?>">
                <?php foreach ($this->subcategories as $subcategory) : ?>
                    <div class="col"><?php echo $this->renderSubcategory($subcategory); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
 <?php // Поиск по каталогу (параметр q): строка с числом найденного и ссылкой «Сбросить». ?>
    <?php if ($this->searchQuery !== '') : ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 dharmaSearchNote" role="status">
            <span><?php echo $this->escape(Text::sprintf('COM_DHARMASHOP_SEARCH_FOUND', $this->searchQuery, $this->total)); ?></span>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->escape($this->categoryRoute((int) $this->item->id)); ?>"><?php echo Text::_('COM_DHARMASHOP_SEARCH_RESET'); ?></a>
        </div>
    <?php endif; ?>
    <form action="<?php echo $this->escape($this->categoryRoute((int) $this->item->id)); ?>" method="get" class="mb-4" data-dharmashop-listing-controls>
        <?php if ($this->searchQuery !== '') : ?><input type="hidden" name="filter[search]" value="<?php echo $this->escape($this->searchQuery); ?>"><?php endif; ?>
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
            <div>
                <label for="dharmashop-sort" class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_SORT_LABEL'); ?></label>
                <select id="dharmashop-sort" name="sort" class="form-select">
                    <?php foreach ($this->sortOptions as $value => $label) : ?>
                        <option value="<?php echo $this->escape($value); ?>"<?php echo $this->ordering === $value ? ' selected' : ''; ?>><?php echo $this->escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-sm-end gap-3">
                <fieldset class="m-0 p-0 border-0">
                    <legend class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_VIEW_LABEL'); ?></legend>
                    <div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_DHARMASHOP_VIEW_LABEL'); ?>">
                        <?php foreach ($modes as $value => $label) : ?>
                            <input type="radio" class="btn-check" name="product_view" id="dharmashop-view-<?php echo $value; ?>" value="<?php echo $value; ?>"<?php echo $this->productView === $value ? ' checked' : ''; ?>>
                            <label class="btn btn-outline-primary" for="dharmashop-view-<?php echo $value; ?>" title="<?php echo $this->escape(Text::_($label)); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="<?php echo $icons[$value]; ?>"/></svg>
                                <span class="visually-hidden"><?php echo Text::_($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button type="submit" class="btn btn-primary" data-dharmashop-listing-submit><?php echo Text::_('COM_DHARMASHOP_APPLY'); ?></button>
            </div>
        </div>
    </form>
    <?php if ($this->productCount !== null || $this->priceRange !== '') : ?>
        <div class="d-flex flex-wrap gap-3 text-body-secondary mb-3">
            <?php if ($this->productCount !== null) : ?><span><?php echo $this->escape(Text::sprintf('COM_DHARMASHOP_CARD_PRODUCT_COUNT', $this->productCount)); ?></span><?php endif; ?>
            <?php if ($this->priceRange !== '') : ?><span><?php echo $this->escape(Text::sprintf('COM_DHARMASHOP_CARD_PRICE_RANGE', $this->priceRange)); ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php // Список товаров и ссылки порций: этот же блок отдаёт AJAX-фрагмент (RawView), JavaScript заменяет им содержимое без смены адреса. ?>
    <div data-dharmashop-listing aria-live="polite"><?php echo $this->loadTemplate('items'); ?></div>
    <?php if ($this->start === 0 && trim((string) $this->item->description) !== '') : ?><div class="mt-4 dharmaFulltext"><?php echo $this->text((string) $this->item->description); ?></div><?php endif; ?>
    <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $this->item->fields ?? [], 'context' => 'page']); ?>
    <?php echo $hooks->render('afterContent'); ?>
</section>
</div>
