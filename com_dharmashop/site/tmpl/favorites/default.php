<?php
declare(strict_types=1);
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Site\View\Favorites\HtmlView $this */
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$renderCard = function (object $item): string {
    $data = $this->cardRenderer->data($item, $this->cardLayout, 'favorites.item', 'favorites', true, [
        'cardLayoutSource' => $this->cardLayoutSource,
        'favoritesPage' => true,
        'fieldColumns' => [],
    ]);

    return $this->cardRenderer->render($data);
};
?>
<div id="dharma_shop" class="dharmaFavorites">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <?php if ($this->showHeading) : ?>
            <h1 class="h2 mb-0"><?php echo $this->escape($this->pageHeading); ?> <span class="text-body-secondary" data-favorites-page-count>(<?php echo (int) $this->count; ?>)</span></h1>
        <?php endif; ?>
        <?php if ($this->count > 0) : ?>
            <form method="post" action="<?php echo $escape($this->clearUrl); ?>" data-favorites-clear-form>
                <input type="hidden" name="<?php echo $escape($this->favoritesTokenName); ?>" value="1">
                <button class="btn btn-outline-secondary" type="submit"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_CLEAR'); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (trim($this->introText) !== '') : ?>
        <div class="mb-4 dharmaIntro"><?php echo $this->text($this->introText); ?></div>
    <?php endif; ?>
    <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 d-none" role="status" aria-live="polite" data-favorites-undo hidden>
        <span><?php echo Text::_('COM_DHARMASHOP_FAVORITES_REMOVED'); ?></span>
        <button class="btn btn-sm btn-outline-success" type="button" data-favorites-undo-button><?php echo Text::_('COM_DHARMASHOP_FAVORITES_UNDO'); ?></button>
    </div>
    <?php if (!$this->items) : ?>
        <div data-favorites-empty-state>
            <div class="alert alert-info"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_EMPTY'); ?></div>
            <a class="btn btn-outline-primary" href="<?php echo $escape(Route::_('index.php?option=com_dharmashop&view=categories', false)); ?>"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_CATALOG'); ?></a>
        </div>
    <?php else : ?>
        <div class="d-none" data-favorites-empty-state hidden>
            <div class="alert alert-info"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_EMPTY'); ?></div>
            <a class="btn btn-outline-primary" href="<?php echo $escape(Route::_('index.php?option=com_dharmashop&view=categories', false)); ?>"><?php echo Text::_('COM_DHARMASHOP_FAVORITES_CATALOG'); ?></a>
        </div>
        <div data-favorites-list>
            <?php if ($this->cardLayout === 'table') : ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle dharmaProductsTable">
                        <caption class="visually-hidden"><?php echo $this->escape($this->pageHeading); ?></caption>
                        <thead><tr><th scope="col"><?php echo Text::_('COM_DHARMASHOP_TABLE_PRODUCT'); ?></th><th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_TABLE_PRICE'); ?></th></tr></thead>
                        <tbody><?php foreach ($this->items as $item) : ?><?php echo $renderCard($item); ?><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="<?php echo $this->cardLayout === 'list' ? 'row row-cols-1 g-3' : 'row row-cols-1 row-cols-md-' . min(2, $this->columns) . ' row-cols-xl-' . $this->columns . ' g-3'; ?>">
                    <?php foreach ($this->items as $item) : ?><div class="col"><?php echo $renderCard($item); ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (trim($this->fullText) !== '') : ?>
        <div class="mt-4 dharmaFulltext"><?php echo $this->text($this->fullText); ?></div>
    <?php endif; ?>
</div>
