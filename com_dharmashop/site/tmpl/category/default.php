<?php
/** Страница списка товаров; разметка товара находится в отдельном JLayout. */
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Site\Support\LayoutHooks;
use Joomla\CMS\Language\Text;

$hooks = new LayoutHooks('category', 'category', $this->item, $this->params, $this->items, $this->seo);
$modes = ['grid' => 'COM_DHARMASHOP_VIEW_GRID', 'list' => 'COM_DHARMASHOP_VIEW_LIST', 'table' => 'COM_DHARMASHOP_VIEW_TABLE'];
?>
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
    <?php if (trim((string) $this->item->params->get('introtext', '')) !== '') : ?>
        <div class="mb-4"><?php echo $this->text((string) $this->item->params->get('introtext')); ?></div>
    <?php endif; ?>
    <?php if ($this->subcategories) : ?>
        <section class="mb-5" aria-labelledby="dharmashop-subcategories-title">
            <h2 id="dharmashop-subcategories-title" class="h4 mb-3"><?php echo Text::_($this->item->id ? 'COM_DHARMASHOP_SUBCATEGORIES' : 'COM_DHARMASHOP_CATEGORIES'); ?></h2>
            <div class="<?php echo $this->escape($this->subcategoryGridClasses); ?>">
                <?php foreach ($this->subcategories as $subcategory) : ?>
                    <div class="col"><?php echo $this->renderSubcategory($subcategory); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <form action="<?php echo $this->escape($this->categoryRoute((int) $this->item->id)); ?>" method="get" class="mb-4" data-dharmashop-listing-controls>
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
            <div>
                <label for="dharmashop-sort" class="form-label"><?php echo Text::_('COM_DHARMASHOP_SORT_LABEL'); ?></label>
                <select id="dharmashop-sort" name="sort" class="form-select">
                    <?php foreach ($this->sortOptions as $value => $label) : ?>
                        <option value="<?php echo $this->escape($value); ?>"<?php echo $this->ordering === $value ? ' selected' : ''; ?>><?php echo $this->escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-sm-end gap-3">
                <fieldset class="m-0 p-0 border-0">
                    <legend class="form-label fs-6 float-none w-auto"><?php echo Text::_('COM_DHARMASHOP_VIEW_LABEL'); ?></legend>
                    <div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_DHARMASHOP_VIEW_LABEL'); ?>">
                        <?php foreach ($modes as $value => $label) : ?>
                            <input type="radio" class="btn-check" name="product_view" id="dharmashop-view-<?php echo $value; ?>" value="<?php echo $value; ?>"<?php echo $this->productView === $value ? ' checked' : ''; ?>>
                            <label class="btn btn-outline-primary" for="dharmashop-view-<?php echo $value; ?>"><?php echo Text::_($label); ?></label>
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
    <?php if (trim((string) $this->item->description) !== '') : ?><div class="mt-4"><?php echo $this->text((string) $this->item->description); ?></div><?php endif; ?>
    <?php echo $hooks->render('afterContent'); ?>
</section>
