<?php
/**
 * Строка поиска товаров: серое поле без рамки и лупа справа, обычная GET-форма на страницу результатов (работает без JavaScript).
 * Подсказки при вводе строит скрипт search.js: форма с `data-dharmashop-search`, поле `[data-dharmashop-search-input]`, контейнер
 * `[data-dharmashop-search-results]` и шаблоны `<template data-dharmashop-search-template="item|category|all|message">` — их разметку
 * можно менять переопределением (`templates/<шаблон>/html/mod_dharmashop_search/default.php`); значения подставляются в элементы с
 * `data-field` (title, image, sku, category, price, stock, count, label, message), отсутствующие элементы пропускаются.
 * Никаких inline-скриптов и стилей, только классы Bootstrap.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$flag = static fn (string $name, int $default = 1): bool => (int) $params->get($name, $default) === 1;
$id = (int) $module->id;
$placeholder = trim((string) $params->get('placeholder', '')) !== '' ? (string) $params->get('placeholder') : Text::_('MOD_DHARMASHOP_SEARCH_PLACEHOLDER_DEFAULT');
$suggest = $flag('show_suggestions');
$showButton = $flag('show_button');
$buttonText = trim((string) $params->get('button_text', ''));
$buttonIcon = $flag('button_icon');
$sfx = $escape(trim((string) $params->get('moduleclass_sfx', '')));
?>
<form action="<?php echo $escape($action); ?>" method="get" role="search" class="position-relative dharmaSearchModule <?php echo $sfx; ?>" id="dharmashop-search-form-<?php echo $id; ?>"
    <?php if ($suggest) : ?>data-dharmashop-search
    data-suggest-url="<?php echo $escape($suggestUrl); ?>"
    data-min-chars="<?php echo (int) $params->get('min_chars', 2); ?>"
    data-delay="<?php echo (int) $params->get('delay', 300); ?>"
    data-limit="<?php echo (int) $params->get('max_results', 6); ?>"
    data-all="<?php echo $flag('show_all') ? 1 : 0; ?>"
    data-categories="<?php echo $flag('show_categories') ? 1 : 0; ?>"<?php endif; ?>>
    <?php foreach ($hidden as $name => $value) : ?>
        <input type="hidden" name="<?php echo $escape($name); ?>" value="<?php echo $escape($value); ?>">
    <?php endforeach; ?>
    <div class="input-group flex-nowrap rounded bg-body-tertiary">
        <label class="visually-hidden" for="dharmashop-search-<?php echo $id; ?>"><?php echo Text::_('MOD_DHARMASHOP_SEARCH_LABEL'); ?></label>
        <input type="search" name="q" id="dharmashop-search-<?php echo $id; ?>" class="<?php echo $escape($inputClass); ?>" maxlength="100" autocomplete="off"
            value="<?php echo $escape($query); ?>" placeholder="<?php echo $escape($placeholder); ?>" data-dharmashop-search-input
            <?php if ($suggest) : ?>role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="dharmashop-search-results-<?php echo $id; ?>"<?php endif; ?>>
        <?php if ($showButton) : ?>
            <button type="submit" class="<?php echo $escape($buttonClass); ?>"<?php echo $buttonText === '' ? ' aria-label="' . $escape(Text::_('MOD_DHARMASHOP_SEARCH_BUTTON_DEFAULT')) . '"' : ''; ?>>
                <?php if ($buttonIcon) : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5.5 5.5"/></svg>
                <?php endif; ?>
                <?php if ($buttonText !== '') : ?><span><?php echo $escape($buttonText); ?></span><?php endif; ?>
            </button>
        <?php endif; ?>
    </div>
    <?php if ($suggest) : ?>
        <div class="dropdown-menu w-100 mt-2 p-0 border-0 shadow-lg position-absolute top-100 start-0" id="dharmashop-search-results-<?php echo $id; ?>" role="listbox" data-dharmashop-search-results></div>

        <?php // Разметка подсказок: скрипт клонирует эти шаблоны. Для своей вёрстки переопределите этот файл. ?>
        <template data-dharmashop-search-template="category">
            <a class="badge rounded-pill text-bg-light border fw-normal text-decoration-none py-2 px-3" href="#"><span data-field="title"></span> <span class="text-body-secondary" data-field="count"></span></a>
        </template>
        <template data-dharmashop-search-template="item">
            <a class="dropdown-item d-flex align-items-center gap-3 py-2 px-3 text-wrap" role="option" href="#">
                <?php if ($flag('show_image')) : ?><img class="rounded flex-shrink-0 object-fit-contain bg-white border" width="56" height="56" alt="" loading="lazy" data-field="image"><?php endif; ?>
                <span class="flex-grow-1 lh-sm">
                    <?php if ($flag('show_category')) : ?><span class="d-block small opacity-75" data-field="category"></span><?php endif; ?>
                    <span class="d-block fw-semibold" data-field="title"></span>
                    <?php if ($flag('show_sku')) : ?><span class="d-block small opacity-75" data-field="sku"></span><?php endif; ?>
                </span>
                <?php if ($flag('show_price')) : ?>
                    <span class="flex-shrink-0 text-end lh-sm"><span class="d-block fw-bold" data-field="price"></span><span class="d-block small opacity-75" data-field="stock"></span></span>
                <?php endif; ?>
            </a>
        </template>
        <template data-dharmashop-search-template="all">
            <a class="dropdown-item text-center fw-semibold py-3 border-top" role="option" href="#" data-field="label"></a>
        </template>
        <template data-dharmashop-search-template="message">
            <div class="px-3 py-3 text-body-secondary" data-field="message"></div>
        </template>
    <?php endif; ?>
</form>
