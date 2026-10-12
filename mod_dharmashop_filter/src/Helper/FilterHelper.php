<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    mod_dharmashop_filter
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Module\DharmaShopFilter\Site\Helper;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinition;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterField;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterState;
use Dharma\Component\DharmaShop\Site\Model\CategoryModel;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Готовит данные модуля фильтра (C84): модель страницы списка (категория, бренд, бейдж, поиск) даёт определения, выбор посетителя, значения
 * и числа; здесь они собираются в разделы формы по настройкам модуля. Вывод — шаблон и макеты (переопределяются в шаблоне сайта).
 */
final class FilterHelper
{
    /** Страницы, на которых фильтр применим (вид компонента → модель). */
    private const MODELS = ['category' => 'Category', 'brand' => 'Brand', 'badge' => 'Badge', 'search' => 'Search'];

    public function __construct(private readonly CMSApplicationInterface $app, private readonly Registry $params, private readonly int $moduleId = 0)
    {
    }

    /**
     * @return  array<string, mixed>|null  данные для шаблона или null — на этой странице фильтра нет
     */
    public function build(): ?array
    {
        $input = $this->app->getInput();
        $settings = ComponentHelper::getParams('com_dharmashop');

        if ($input->getCmd('option') !== 'com_dharmashop' || !isset(self::MODELS[$input->getCmd('view')]) || (int) $settings->get('filter_enabled', 1) !== 1) {
            return null;
        }

        /** @var CategoryModel $model */
        $model = $this->app->bootComponent('com_dharmashop')->getMVCFactory()->createModel(self::MODELS[$input->getCmd('view')], 'Site', ['ignore_request' => true]);

        // Страница категории: текущая, весь каталог или выбранная в настройках модуля.
        $mode = (string) $this->params->get('category_mode', 'current');

        if ($input->getCmd('view') === 'category') {
            if ($mode === 'root') {
                $model->forCategory(0);
            } elseif ($mode === 'fixed' && (int) $this->params->get('category', 0) > 0) {
                $model->forCategory((int) $this->params->get('category', 0));
            }
        }

        $initiator = \Dharma\Component\DharmaShop\Site\Support\FilterRequest::params();
        $model->setFilterModule(count($initiator->toArray()) ? $initiator : $this->params);
        $definition = $model->getFilterDefinition();
        $state = $model->getFilterState();
        $counts = (string) $this->params->get('counts', 'inherit');
        $counts = in_array($counts, ['off', 'base', 'live'], true) ? $counts : (string) $settings->get('filter_counts', 'base');
        $counts = in_array($counts, ['off', 'base', 'live'], true) ? $counts : 'base';
        $cascadeSetting = (string) $this->params->get('cascade', 'inherit');
        $cascade = in_array($cascadeSetting, ['on', 'off'], true)
            ? $cascadeSetting === 'on' : (int) $settings->get('filter_cascade', 1) === 1;
        // Доступность значений и показ чисел — независимые настройки. При каскаде числа также контекстные.
        $facets = $model->getFacets($cascade ? 'live' : $counts);
        if (!empty($facets['unavailable'])) { $counts = 'off'; }
        $catid = (int) $model->getParent()->id;
        $services = $this->app->bootComponent('com_dharmashop')->getServices();
        [$action, $hidden] = $this->target($input);
        $sections = $this->sections($definition, $state, $facets, $counts, $catid, $services, $action, $hidden);
        $total = $model->getTotal();

        return [
            'orientation' => (string) $this->params->get('orientation', 'vertical') === 'horizontal' ? 'horizontal' : 'vertical',
            'action' => $action,
            'hidden' => $hidden,
            'sections' => $sections,
            'chips' => $this->params->get('show_active_chips', 1) ? $this->chips($definition, $state, $action, $hidden) : [],
            'resetUrl' => $this->url($action, $hidden, []),
            'active' => $state->activeCount(),
            'total' => $total,
            'totalCapped' => $model->totalCapped,
            'approximate' => (bool) $facets['approximate'],
            'showCounts' => $counts !== 'off',
            'cascade' => $cascade,
            'collapsible' => (int) $this->params->get('collapsible_groups', 1) === 1,
        ];
    }

    /**
     * Разделы формы по настройкам: порядок и показ — `fields_config`; без неё — подкатегории, бренд, бейджи, цена, все поля, наличие.
     *
     * @param   array<string, mixed>  $facets
     *
     * @return  array<int, array<string, mixed>>
     */
    private function sections(FilterDefinition $definition, FilterState $state, array $facets, string $counts, int $catid, object $services, string $action, array $hidden): array
    {
        $config = [];

        foreach ((array) $this->params->get('fields_config', []) as $row) {
            $row = (array) $row;

            if (!empty($row['field'])) {
                $config[] = $row;
            }
        }

        if (!$config) {
            foreach (['@cat', '@brand', '@badge', '@price'] as $key) {
                $config[] = ['field' => $key];
            }

            foreach ($definition->fields as $field) {
                $config[] = ['field' => $field->alias];
            }

            $config[] = ['field' => '@stock'];
            $config[] = ['field' => '@sale'];
        }

        $sections = [];
        $empty = (string) $this->params->get('empty_options', 'hide');
        $hideSingle = (int) $this->params->get('hide_single_value_fields', 0) === 1;
        $engine = $services->filter()->facets();

        foreach ($config as $row) {
            if (isset($row['show']) && (int) $row['show'] === 0) {
                continue;
            }

            $key = (string) $row['field'];
            $title = trim((string) ($row['title'] ?? ''));
            $control = (string) ($row['control'] ?? 'auto');
            $expanded = (int) ($row['expanded'] ?? ($definition->fieldByAlias($key)?->settings['expanded'] ?? 1)) === 1;
            $section = null;

            if ($key === '@price') {
                $section = $this->price($state, $definition, $facets['price'] ?? null, $action, $hidden);
            } elseif ($key === '@stock') {
                $section = ['kind' => 'stock', 'name' => 'filter[stock]', 'checked' => $state->inStock, 'control' => 'switch', 'options' => [], 'title' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_STOCK_LABEL')];
            } elseif ($key === '@sale') {
                $section = ['kind' => 'sale', 'name' => 'filter[sale]', 'checked' => $state->onSale, 'control' => 'switch', 'options' => [], 'title' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_SALE_LABEL')];
            } elseif (in_array($key, ['@brand', '@badge', '@cat'], true)) {
                $section = $this->directory($key, $definition, $state, $catid, $engine, $facets, $empty, $counts !== 'off', $control);
            } elseif (($field = $definition->fieldByAlias($key)) !== null) {
                $section = $this->field($field, $state, $facets, $empty, $counts !== 'off', $control, (string) ($row['logic'] ?? 'inherit'));
            }

            if ($section === null) {
                continue;
            }

            if ($hideSingle && in_array($section['kind'], ['field', 'brand', 'badge', 'cat'], true) && count($section['options']) <= 1 && empty($section['selected'])) {
                continue;
            }

            $section['key'] = $key;
            $section['title'] = $title !== '' ? $title : (string) ($section['title'] ?? $key);
            $section['expanded'] = $expanded || !empty($section['selected']);
            $sections[] = $section;
        }

        return $sections;
    }

    /** @param array<string, mixed> $facets @return array<string, mixed>|null */
    private function field(FilterField $field, FilterState $state, array $facets, string $empty, bool $showCounts, string $control, string $logic): ?array
    {
        if ($field->kind !== FilterField::OPTION) {
            $bounds = $facets['ranges'][$field->id] ?? null;
            $value = $state->ranges[$field->id] ?? ['min' => null, 'max' => null];

            if ($bounds === null && $value['min'] === null && $value['max'] === null) {
                return null;
            }

            return ['kind' => 'range', 'title' => $field->title . ($field->unit !== '' ? ', ' . $field->unit : ''), 'name' => 'filter[f][' . $field->alias . ']', 'control' => 'range',
                'bounds' => $bounds, 'value' => $value, 'selected' => $value['min'] !== null || $value['max'] !== null, 'slider' => (int) $this->params->get('range_slider', 0) === 1, 'options' => [], 'step' => $field->kind === FilterField::RANGE || ($bounds && (fmod($bounds['min'], 1) !== 0.0 || fmod($bounds['max'], 1) !== 0.0)) ? 'any' : '1'];
        }

        $present = $facets['options'][$field->id] ?? [];
        $chosen = $state->options[$field->id] ?? [];
        $options = [];

        foreach ($field->options as $option) {
            $count = (int) ($present[$option->id] ?? 0);
            $selected = in_array($option->id, $chosen, true);

            if ($count === 0 && !$selected && $empty === 'hide') {
                continue;
            }

            $options[] = ['value' => $option->alias, 'label' => $option->title, 'count' => $showCounts ? $count : null, 'checked' => $selected, 'disabled' => $count === 0 && !$selected && $empty === 'disable',
                'color' => $option->color, 'image' => $option->image, 'weight' => $count];
        }

        $options = $this->sortOptions($options, (string) ($field->settings['sort'] ?? $this->params->get('options_sort', 'field')));

        if (!$options) {
            return null;
        }

        $effective = $logic === 'inherit' ? $field->logic() : ($logic === 'and' ? 'and' : 'or');

        return ['kind' => 'field', 'title' => $field->title, 'name' => 'filter[f][' . $field->alias . '][]', 'control' => $this->control($control, $field),
            'options' => $options, 'selected' => (bool) $chosen, 'logic' => $effective, 'limit' => (int) ($field->settings['limit'] ?? 0) ?: $this->limit(), 'search' => (int) ($field->settings['search'] ?? 1) === 1, 'swatches' => $field->type === 'color'];
    }

    /** @return array<string, mixed>|null */
    private function directory(string $key, FilterDefinition $definition, FilterState $state, int $catid, object $engine, array $facets, string $empty, bool $showCounts, string $control): ?array
    {
        [$directory, $chosen, $name, $title, $direct] = match ($key) {
            '@brand' => [$definition->brands, $state->brands, 'filter[brand][]', 'MOD_DHARMASHOP_FILTER_SYSTEM_BRAND', true],
            '@badge' => [$definition->badges, $state->badges, 'filter[badge][]', 'MOD_DHARMASHOP_FILTER_SYSTEM_BADGE', true],
            default => [$definition->subcategories, $state->categories, 'filter[cat][]', 'MOD_DHARMASHOP_FILTER_SYSTEM_CAT', false],
        };

        if (!$directory) {
            return null;
        }

        $counts = $facets['directories'][substr($key, 1)] ?? [];
        $options = [];

        foreach ($directory as $id => $row) {
            $count = (int) ($counts[$id] ?? 0);
            $selected = in_array($id, $chosen, true);

            if ($count === 0 && !$selected && $empty === 'hide') {
                continue;
            }

            $options[] = ['value' => $row['alias'], 'label' => $row['title'], 'count' => $showCounts ? $count : null, 'checked' => $selected, 'disabled' => $count === 0 && !$selected && $empty === 'disable', 'color' => null, 'image' => null, 'weight' => $count];
        }

        if (!$options) {
            return null;
        }

        return ['kind' => substr($key, 1), 'title' => Text::_($title), 'name' => $name, 'control' => $control === 'auto' ? 'checkboxes' : $this->control($control, null), 'options' => $options,
            'selected' => (bool) $chosen, 'limit' => $this->limit()];
    }

    /** @param array{min: float, max: float}|null $bounds @return array<string, mixed>|null */
    private function price(FilterState $state, FilterDefinition $definition, ?array $bounds, string $action, array $hidden): ?array
    {
        if ((string) $this->params->get('price_display', 'inputs') === 'none' || ($bounds === null && $state->price === null)) {
            return null;
        }

        $value = $state->price ?? ['min' => null, 'max' => null];
        $presets = [];

        foreach (preg_split('/\R/u', (string) $this->params->get('price_presets', '')) ?: [] as $line) {
            if (preg_match('/^\s*(.+?)\s*=\s*(\d*(?:[.,]\d+)?)\s*-\s*(\d*(?:[.,]\d+)?)\s*$/u', $line, $m)) {
                // Быстрый интервал — ссылка на ту же страницу с выбранными границами цены (остальные условия сохраняются).
                $filter = $state->toArray($definition);
                unset($filter['price']);
                $range = array_filter(['min' => str_replace(',', '.', $m[2]), 'max' => str_replace(',', '.', $m[3])], static fn (string $n): bool => $n !== '');

                if ($range) {
                    $filter['price'] = $range;
                }

                $presets[] = ['label' => $m[1], 'url' => $this->url($action, $hidden, $filter)];
            }
        }

        $step = (int) $this->params->get('price_step', 0);

        return ['kind' => 'price', 'title' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_PRICE'), 'name' => 'filter[price]', 'control' => 'range', 'bounds' => $bounds, 'value' => $value,
            'selected' => $state->price !== null, 'slider' => (string) $this->params->get('price_display', 'inputs') === 'slider', 'options' => [], 'step' => $step > 0 ? (string) $step : '1', 'presets' => $presets];
    }

    /** Представление значений: авто — флажки (мультивыбор); radio и select — один выбор; buttons и swatches — кнопки и образцы. */
    private function control(string $control, ?FilterField $field): string
    {
        $control = $control === 'auto' ? (string) ($field?->settings['control'] ?? 'auto') : $control;

        return in_array($control, ['select', 'checkboxes', 'radio', 'buttons', 'swatches', 'images'], true) ? $control : 'checkboxes';
    }

    /** @param array<int, array<string, mixed>> $options @return array<int, array<string, mixed>> */
    private function sortOptions(array $options, string $mode): array
    {
        if ($mode === 'alpha') {
            usort($options, static fn (array $a, array $b): int => mb_strtolower((string) $a['label']) <=> mb_strtolower((string) $b['label']));
        } elseif ($mode === 'count') {
            usort($options, static fn (array $a, array $b): int => (int) $b['weight'] <=> (int) $a['weight']);
        }

        return $options;
    }

    /** Сколько значений показывать сразу (0 — все); остальные — за «Ещё». */
    private function limit(): int
    {
        return (string) $this->params->get('checkbox_limit_mode', 'all') === 'limit' ? max(1, (int) $this->params->get('checkbox_visible_count', 6)) : 0;
    }

    /**
     * Адрес отправки формы и скрытые поля: страница списка (пункт меню из настроек или текущая) плюс сортировка, вид и поисковый запрос.
     *
     * @return  array{0: string, 1: array<string, string>}
     */
    private function target(\Joomla\Input\Input $input): array
    {
        $menuItem = (int) $this->params->get('menu_item', 0);
        $uri = $menuItem > 0 ? Route::_('index.php?Itemid=' . $menuItem, false) : \Joomla\CMS\Uri\Uri::getInstance()->toString(['path']);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $path = $query === '' ? $uri : substr($uri, 0, (int) strpos($uri, '?'));
        parse_str($query, $existing);
        $hidden = array_filter($existing, 'is_scalar');

        // Страница в адресе без SEF (`index.php?option=…&view=…`): параметры остаются скрытыми полями GET-формы.
        if ($menuItem <= 0 && $query === '' && !str_contains($path, '/')) {
            $hidden = [];
        }

        foreach (['sort', 'product_view'] as $name) {
            if ($input->get->exists($name)) {
                $hidden[$name] = (string) $input->get->getCmd($name);
            }
        }

        if (!(int) $this->app->get('sef', 0)) {
            foreach (['option', 'view', 'id', 'Itemid'] as $name) {
                if ($input->exists($name)) { $hidden[$name] = $input->getString($name); }
            }
        }
        if ($input->getCmd('view') === 'search') { $hidden['q'] = $input->getString('q'); }

        $filter = $input->get('filter', [], 'array');

        if (is_array($filter) && isset($filter['search']) && is_scalar($filter['search']) && trim((string) $filter['search']) !== '') {
            $hidden['filter[search]'] = mb_substr(trim((string) $filter['search']), 0, 100);
        }

        if ($this->moduleId > 0) { $hidden['filter_module'] = (string) $this->moduleId; }
        return [$path, array_map('strval', $hidden)];
    }

    /** Адрес страницы с условиями фильтра: путь формы + скрытые параметры + массив `filter`. @param array<string, string> $hidden @param array<string, mixed> $filter */
    private function url(string $action, array $hidden, array $filter): string
    {
        $query = [];

        foreach ($hidden as $name => $value) {
            if ($name === 'filter[search]') {
                $filter['search'] = $value;

                continue;
            }

            $query[$name] = $value;
        }

        if ($filter) {
            $query['filter'] = $filter;
        }

        if (!$filter) { unset($query['filter_module']); }

        // Списки значений — `[]`, а не `[0]`, `[1]` (как отправляет форма и как требует канонический адрес).
        $string = $query ? (string) preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($query, '', '&', PHP_QUERY_RFC3986)) : '';

        return $action . ($string !== '' ? (str_contains($action, '?') ? '&' : '?') . $string : '');
    }

    /**
     * Выбранные условия в виде «чипов» с адресом сброса одного условия.
     *
     * @param   array<string, string>  $hidden
     *
     * @return  array<int, array{label: string, url: string}>
     */
    private function chips(FilterDefinition $definition, FilterState $state, string $action, array $hidden): array
    {
        $current = $state->toArray($definition);
        $chips = [];


        // Чипы по значениям: каждое выбранное значение снимается отдельно.
        foreach ($definition->fields as $field) {
            if (isset($state->options[$field->id])) {
                foreach ($field->options as $option) {
                    if (in_array($option->id, $state->options[$field->id], true)) {
                        $filter = $current;
                        $filter['f'][$field->alias] = array_values(array_diff((array) ($filter['f'][$field->alias] ?? []), [$option->alias]));

                        if (!$filter['f'][$field->alias]) {
                            unset($filter['f'][$field->alias]);
                        }

                        if (empty($filter['f'])) {
                            unset($filter['f']);
                        }

                        $chips[] = ['label' => $field->title . ': ' . $option->title, 'url' => $this->url($action, $hidden, $filter)];
                    }
                }
            } elseif (isset($state->ranges[$field->id])) {
                $filter = $current;
                unset($filter['f'][$field->alias]);

                if (empty($filter['f'])) {
                    unset($filter['f']);
                }

                $b = $state->ranges[$field->id];
                $chips[] = ['label' => $field->title . ': ' . $this->rangeText($b) . ($field->unit !== '' ? ' ' . $field->unit : ''), 'url' => $this->url($action, $hidden, $filter)];
            }
        }

        if ($state->price !== null) {
            $filter = $current;
            unset($filter['price']);
            $chips[] = ['label' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_PRICE') . ': ' . $this->rangeText($state->price), 'url' => $this->url($action, $hidden, $filter)];
        }

        foreach ([['brand', $definition->brands, $state->brands], ['badge', $definition->badges, $state->badges], ['cat', $definition->subcategories, $state->categories]] as [$key, $directory, $ids]) {
            foreach ($directory as $id => $row) {
                if (in_array($id, $ids, true)) {
                    $filter = $current;
                    $filter[$key] = array_values(array_diff((array) ($filter[$key] ?? []), [$row['alias']]));

                    if (!$filter[$key]) {
                        unset($filter[$key]);
                    }

                    $chips[] = ['label' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_' . strtoupper($key)) . ': ' . $row['title'], 'url' => $this->url($action, $hidden, $filter)];
                }
            }
        }

        if ($state->inStock) {
            $filter = $current;
            unset($filter['stock']);
            $chips[] = ['label' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_STOCK_LABEL'), 'url' => $this->url($action, $hidden, $filter)];
        }

        if ($state->onSale) { $filter = $current; unset($filter['sale']); $chips[] = ['label' => Text::_('MOD_DHARMASHOP_FILTER_SYSTEM_SALE_LABEL'), 'url' => $this->url($action, $hidden, $filter)]; }
        return $chips;
    }

    /** @param array{min: ?float, max: ?float} $bounds */
    private function rangeText(array $bounds): string
    {
        $format = static fn (float $n): string => str_replace('.', ',', rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'));

        if ($bounds['min'] !== null && $bounds['max'] !== null) {
            return $format($bounds['min']) . ' – ' . $format($bounds['max']);
        }

        return $bounds['min'] !== null ? Text::_('MOD_DHARMASHOP_FILTER_FROM') . ' ' . $format($bounds['min']) : Text::_('MOD_DHARMASHOP_FILTER_TO') . ' ' . $format((float) $bounds['max']);
    }
}
