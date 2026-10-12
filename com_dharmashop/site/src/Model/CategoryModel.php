<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinition;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterState;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Dharma\Component\DharmaShop\Administrator\Filter\ScopedFacets;
use Dharma\Component\DharmaShop\Administrator\Filter\QueryBudget;
use Dharma\Component\DharmaShop\Site\Support\FilterRequest;
use Dharma\Component\DharmaShop\Site\Search\ProductSearch;
use Dharma\Component\DharmaShop\Site\Support\SaleCountdown;
use Dharma\Component\DharmaShop\Site\Support\GroupListing;
use Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer;
use Dharma\Component\DharmaShop\Site\Support\VariantSelection;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Dharma\Component\DharmaShop\Site\Support\ProductFields;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Товары категории: проверка категории и её детей общая со списком категорий. */
class CategoryModel extends CategoriesModel
{
    /** Колонки сортировки выбираются только из этого списка. */
    public const SORTS = ['order', 'price_asc', 'price_desc', 'new', 'title', 'relevance'];

    /** @var array<string, string> значения параметров поискового подзапроса (переменные нужны для привязки по ссылке) */
    private array $searchBinds = [];

    /** @var array<string, mixed> значения параметров вложенного запроса кандидатов (для привязки по ссылке) */
    private array $embeddedBinds = [];

    /** Мягкий режим поиска включён (null — ещё не определено) */
    private ?bool $searchFuzzy = null;

    private ?FilterDefinition $filterDefinition = null;
    private ?FilterState $filterState = null;

    /** Индекс витрины для подсказки `FORCE INDEX` страницы списка (выбирает планировщик фильтра; null — выбор оптимизатора). */
    private ?string $indexHint = null;
    private ?GroupListing $groupListing = null;

    /** Переопределяемый режим выдачи из общих настроек → категории → пункта меню. */
    private ?string $listingMode = null;

    private ?Registry $filterModule = null;

    public function setFilterModule(Registry $params): void
    {
        $this->filterModule = clone $params;
        $this->filterDefinition = $this->filterState = null;
    }

    private function filterModule(): Registry
    {
        return $this->filterModule ??= FilterRequest::params();
    }

    public function setListingMode(string $mode): void
    {
        $this->listingMode = in_array($mode, ['products', 'groups_products'], true) ? $mode : null;
    }

    public function groupsEnabled(): bool
    {
        if (!$this->getFilterState()->isEmpty()) {
            $policy = (string) $this->filterModule()->get('filtered_listing', 'inherit');
            if (in_array($policy, ['groups', 'products'], true)) { return $policy === 'groups'; }
        }
        $mode = $this->listingMode ?? (string) ComponentHelper::getParams('com_dharmashop')->get('category_listing_mode', 'products');

        return $mode === 'groups_products';
    }

    private function groupListing(): GroupListing
    {
        return $this->groupListing ??= new GroupListing($this->getDatabase());
    }

    /** Число найденных товаров с фильтром упёрлось в потолок подсчёта (показывать «N+»). */
    public bool $totalCapped = false;

    /**
     * Нужен ли мягкий режим поиска: он включается, только если обычный запрос ничего не нашёл (один дополнительный COUNT на запрос)
     * и разрешён настройкой «Мягкий поиск при отсутствии результатов».
     */
    private function searchIsFuzzy(string $text): bool
    {
        if ($this->searchFuzzy !== null) {
            return $this->searchFuzzy;
        }

        $this->searchFuzzy = false;

        if ((int) ComponentHelper::getParams('com_dharmashop')->get('search_fuzzy', 1) === 1) {
            $this->searchFuzzy = (int) $this->getDatabase()->setQuery($this->productsQuery(forListing: $this->groupsEnabled())->select('COUNT(*)'))->loadResult() === 0;
        }

        return $this->searchFuzzy;
    }

    /** Что можно фильтровать на этой странице (кэш). */
    public function getFilterDefinition(): FilterDefinition
    {
        if ($this->filterDefinition === null) {
            $catid = (int) $this->getParent()->id;
            $this->filterDefinition = FilterRequest::definition((new \Dharma\Component\DharmaShop\Administrator\Filter\FilterService($this->getDatabase()))->definition($catid), $this->filterModule());
        }

        return $this->filterDefinition;
    }

    /** Выбор посетителя в фильтре (`filter[f]`, `filter[price]`, …) по справочнику страницы; пустое состояние — без фильтра. */
    public function getFilterState(): FilterState
    {
        if ($this->filterState === null) {
            $input = Factory::getApplication()->getInput()->get('filter', [], 'array');
            $enabled = (int) ComponentHelper::getParams('com_dharmashop')->get('filter_enabled', 1) === 1;
            $services = (new \Dharma\Component\DharmaShop\Administrator\Filter\FilterService($this->getDatabase()));
            $this->filterState = $enabled && is_array($input) && $input ? $services->state($input, $this->getFilterDefinition()) : new FilterState();
        }

        return $this->filterState;
    }

    /**
     * Данные фильтра страницы: у каких значений есть товары, числа, границы. Режим счётчиков: `off` — только наличие по базовому
     * состоянию, `base` — числа базового состояния, `live` — каскад (числа под текущий выбор; при слишком большом числе кандидатов — база).
     *
     * @return  array{options: array<int, array<int, int>>, counts: bool, approximate: bool, ranges: array<int, array{min: float, max: float}>, price: array{min: float, max: float}|null}
     */
    public function getFacets(string $mode = 'base'): array
    {
        $db = $this->getDatabase();
        $grouped = $this->groupsEnabled();
        $definition = $this->getFilterDefinition();
        $source = fn(FilterState $state): QueryInterface => $this->productsQuery($state, forListing: $grouped)
            ->select(['p.id', ($grouped ? GroupListing::unit() : 'p.id') . ' AS unit', 'p.price_min', 'p.price_max']);
        $baseQuery = $source(new FilterState());
        $boundValues = array_map(static fn(object $bound): mixed => $bound->value, $baseQuery->getBounded());
        $cacheKey = md5($db->replacePrefix((string) $baseQuery) . json_encode([$boundValues, $definition, $this->getCurrentUser()->getAuthorisedViewLevels()]));
        $stored = null;
        // Глобальный предрасчёт безопасен только без скрытых категорий/предков и без поискового подмножества.
        if (!$grouped && $this->getQueryText() === '' && $this->baseFacetsAccessible()) {
            $stored = (new \Dharma\Component\DharmaShop\Administrator\Filter\FilterService($this->getDatabase()))->facets()->base((int) $this->getParent()->id);
        }
        return (new ScopedFacets($db))->build($source, $definition, $this->getFilterState(), $mode, $cacheKey, $stored);
    }

    private function baseFacetsAccessible(): bool
    {
        $db = $this->getDatabase();
        $levels = implode(',', array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0]);
        return FilterCache::remember('base-access:' . $db->getPrefix() . ':' . $levels, function () use ($db, $levels): bool {
            $query = $db->createQuery()->select('id')->from('#__categories')
                ->where("extension='com_dharmashop'")->where('(published<>1 OR access NOT IN (' . $levels . '))');
            return !$db->setQuery($query, 0, 1)->loadResult();
        });
    }

    /** Текст поискового запроса (`filter[search]`) страницы списка; пусто — без поиска. */
    public function getQueryText(): string
    {
        return ProductSearch::fromInput(Factory::getApplication()->getInput());
    }

    /**
     * Основные категории найденных товаров с числом товаров (для подсказок поиска): по убыванию числа, не больше $limit.
     *
     * @return  object[]  catid, title, n
     */
    public function getCategoryCounts(int $limit): array
    {
        $db = $this->getDatabase();
        $query = $this->productsQuery()->select([$db->quoteName('p.catid'), $db->quoteName('c.title'), 'COUNT(*) AS n'])
            ->group([$db->quoteName('p.catid'), $db->quoteName('c.title')])->order('n DESC')->order($db->quoteName('p.catid'));

        return $db->setQuery($query, 0, max(1, $limit))->loadObjectList();
    }

    /** Число видимых товаров списка (для постраничной навигации). */
    public function getTotal(): int
    {
        $db = $this->getDatabase();

        $grouped = $this->groupsEnabled();
        $query = $this->productsQuery(forListing: $grouped);
        $unit = $grouped ? GroupListing::unit() : 'p.id';

        // С фильтром точный подсчёт редкого условия по широкой категории дорог: считаем до потолка (по умолчанию 10 001), дальше — «N+».
        if (!$this->getFilterState()->isEmpty()) {
            $cap = max(101, min(100001, (int) ComponentHelper::getParams('com_dharmashop')->get('filter_count_cap', 10001)));
            $query->select('DISTINCT ' . $unit);
            $bindings = array_map(static fn(object $bound): mixed => $bound->value, $query->getBounded());
            $key = 'total:v2:' . md5($db->replacePrefix((string) $query) . json_encode([$bindings, $cap]));
            $found = FilterCache::remember($key, fn(): int => count($db->setQuery(QueryBudget::apply($query), 0, $cap + 1)->loadColumn()));
            $this->totalCapped = $found > $cap;

            return min($found, $cap);
        }

        return (int) $db->setQuery(QueryBudget::apply($query->select($grouped ? 'COUNT(DISTINCT ' . $unit . ')' : 'COUNT(*)')))->loadResult();
    }

    /**
     * Страница товаров: со смещением $start. Сортировка стабильна при равных значениях (второй ключ — id).
     * Смещение идёт по узкому индексу категории; глубокие страницы дороже первых (см. DECISIONS C44).
     *
     * @return array Товары страницы.
     */
    public function getProducts(string $ordering, int $limit, int $start = 0): array
    {
        $ordering = in_array($ordering, static::SORTS, true) ? $ordering : static::SORTS[0];
        $db = $this->getDatabase();
        [$column, $direction] = $this->sortExpression($ordering);
        $grouped = $this->groupsEnabled();
        $sort = $grouped ? $this->groupSortExpression($ordering) : $db->quoteName($column);
        // Форма запроса по плотности условий фильтра (замеры F0): плотное условие — обход индекса сортировки с ранней остановкой.
        $state = $this->getFilterState();
        $this->indexHint = $grouped || $state->isEmpty() || $this->getQueryText() !== '' ? null
            : (new \Dharma\Component\DharmaShop\Administrator\Filter\FilterService($this->getDatabase()))->planner()
                ->indexHint($state, $this->getFilterDefinition(), (int) $this->getParent()->id, $ordering, max(0, $start), $limit);
        $candidates = $this->productsQuery(forListing: $grouped);
        $query = ($grouped ? $this->groupListing()->representatives($candidates, $sort, $direction, (int) $this->getParent()->id) : clone $candidates)
            ->select($db->quoteName(['p.id', 'p.catid', 'p.title', 'p.alias', 'p.cover', 'p.cover_alt',
                'p.introtext', 'p.group_id', 'p.inherit_images', 'p.price_min', 'p.price_max', 'p.price_zero_free', 'p.in_stock', 'p.unit', 'p.qty_min', 'p.qty_step', 'p.params']))
            ->select($db->quoteName('c.title', 'category_title'))
            ->order(($grouped ? 'listing_ranked.listing_sort' : $sort) . ' ' . $direction)->order((!$grouped && (int) $this->getParent()->id > 0 ? 'pc.product_id' : 'p.id') . ' ' . $direction);
        if ($this->indexHint !== null) {
            // Dense first-page scan: keep category order and correlated index probes, stop after LIMIT matches.
            $columns = $query->select->getElements();
            $columns[0] = 'STRAIGHT_JOIN ' . $columns[0];
            $query->clear('select')->select($columns);
        }
        $items = $db->setQuery(QueryBudget::apply($query), max(0, $start), $limit)->loadObjectList();
        $this->indexHint = null;

        $rules = Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->orderRules();

        foreach ($items as $item) {
            $item->params = new Registry($item->params);
            $item->price_free = $rules->priceIsFree($item);
        }

        if ($grouped) {
            // These groups have already passed the listing scope. Enrich only their matching members,
            // without materialising the whole category UNION again for each bounded batch query.
            $groupIds = array_values(array_unique(array_filter(array_map(static fn(object $item): int => (int) $item->group_id, $items))));
            $members = $groupIds ? $this->productsQuery(forListing: true, knownGroups: $groupIds) : $candidates;
            $this->groupListing()->attach($items, $members, $rules);
        }
        foreach ($items as $item) {
            if (GroupCardRenderer::isGroup($item)) {
                $item->listing_scope = ['view' => $this->taxonomy()->pageView, 'id' => (int) $this->getParent()->id,
                    'filter' => (array) Factory::getApplication()->getInput()->get('filter', [], 'array'), 'search' => $this->getQueryText(),
                    'filter_module' => Factory::getApplication()->getInput()->getInt('filter_module'), 'itemid' => Factory::getApplication()->getInput()->getInt('Itemid')];
            }
        }

        return $items;
    }

    /**
     * Данные карточек страницы одним пакетом (без запросов на каждую карточку): обычная цена и размер скидки варианта по умолчанию
     * и бейджи товара (только опубликованные, доступные посетителю). Поля добавляются в объекты товаров: base_price, discount_amount, discount_percent, badges.
     *
     * @param   object[]  $items
     */
    public function attachCardData(array $items, int $imagesLimit = 1): void
    {
        $ajaxGroups = ComponentHelper::getParams('com_dharmashop')->get('group_card_mode', 'simple') === 'ajax';
        $groupItems = array_values(array_filter($items, static fn(object $item): bool => GroupCardRenderer::isGroup($item)));
        $items = array_values(array_filter($items, static fn(object $item): bool => $ajaxGroups || !GroupCardRenderer::isGroup($item)));
        if (!$items) {
            $this->groupListing()->inheritImages($groupItems, 1);
            return;
        }

        $db = $this->getDatabase();
        $ids = array_map(static fn (object $item): int => (int) $item->id, $items);
        ProductFields::attach($db, $items, 'card');
        $list = implode(',', $ids);
        $prices = [];

        foreach ($db->setQuery(
            'SELECT v.product_id, v.sku, vp.price, vp.price_final, vp.discount_start, vp.discount_end FROM ' . $db->quoteName('#__dharmashop_variants', 'v')
            . ' JOIN ' . $db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id'
            . ' WHERE v.product_id IN (' . $list . ') AND v.is_default = 1 AND v.state = 1 AND vp.price_list_id = 1'
        )->loadObjectList() as $row) {
            $prices[(int) $row->product_id] = $row;
        }

        // Галерея товара для карусели в карточке: только если выводится больше одного фото (иначе хватает обложки). 0 — все (не больше 20).
        $images = [];

        if ($imagesLimit !== 1) {
            $limit = $imagesLimit > 0 ? $imagesLimit : 20;

            foreach ($db->setQuery(
                'SELECT product_id, path, alt FROM ' . $db->quoteName('#__dharmashop_field_media')
                . ' WHERE product_id IN (' . $list . ') AND field_id = 0 AND variant_id IS NULL ORDER BY product_id, ordering'
            )->loadObjectList() as $row) {
                if (count($images[(int) $row->product_id] ?? []) < $limit) {
                    $images[(int) $row->product_id][] = (object) ['path' => (string) $row->path, 'alt' => (string) $row->alt];
                }
            }
        }

        $levels = implode(',', array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0]);
        $badges = [];
        $brands = [];

        foreach ($db->setQuery(
            'SELECT pc.product_id, c.id, c.title, c.params, c.extension FROM ' . $db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $db->quoteName('#__categories', 'c') . ' ON c.id = pc.catid'
            . ' WHERE pc.product_id IN (' . $list . ') AND pc.direct = 1 AND c.extension IN (' . $db->quote('com_dharmashop.badge') . ',' . $db->quote('com_dharmashop.brand') . ')'
            . ' AND c.published = 1 AND c.access IN (' . $levels . ') ORDER BY c.lft'
        )->loadObjectList() as $row) {
            $params = new Registry($row->params);

            if ($row->extension === 'com_dharmashop.brand') {
                $brands[(int) $row->product_id] ??= (string) $row->title;
                continue;
            }

            // Настройка бейджа «Показывать в списках»: выключенный бейдж на карточках не выводится.
            if ((int) $params->get('badge_show_list', 1) !== 1) {
                continue;
            }

            $color = (string) $params->get('badge_color', 'primary');
            $badges[(int) $row->product_id][] = (object) [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'color' => in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $color : 'primary',
            ];
        }

        foreach ($items as $item) {
            $id = (int) $item->id;
            $item->badges = $badges[$id] ?? [];
            $item->images = $images[$id] ?? [];
            $item->base_price = null;
            $item->discount_amount = null;
            $item->discount_percent = 0;
            $item->sale_end_timestamp = null;
            $row = $prices[$id] ?? null;
            $item->sku = (string) ($row->sku ?? '');
            $item->brand_title = $brands[$id] ?? '';

            if ($row && $row->price !== null && $row->price_final !== null && (float) $row->price > 0 && (float) $row->price_final < (float) $row->price) {
                $item->base_price = (string) $row->price;
                $item->discount_amount = number_format((float) $row->price - (float) $row->price_final, 2, '.', '');
                $item->discount_percent = (1 - (float) $row->price_final / (float) $row->price) * 100;
                $item->sale_end_timestamp = SaleCountdown::endTimestamp($row->discount_start, $row->discount_end, true);
            }
        }
        $this->groupListing()->inheritImages($groupItems, $ajaxGroups ? $imagesLimit : 1);
    }

    /** AJAX picks one member of the same public candidate set by visible choices; hidden axes are preferences. */
    public function selectGroupCardProduct(int $sourceId, array $changes, int $imagesLimit): object
    {
        if (!$this->groupsEnabled() || ComponentHelper::getParams('com_dharmashop')->get('group_card_mode', 'simple') !== 'ajax' || count($changes) > 6) {
            throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404);
        }
        $db = $this->getDatabase();
        $candidates = $this->productsQuery(forListing: true);
        $load = function (int $id) use ($db, $candidates): ?object {
            $query = clone $candidates;
            $query->select('p.id,p.catid,p.title,p.alias,p.cover,p.cover_alt,p.introtext,p.group_id,p.inherit_images,p.price_min,p.price_max,p.price_zero_free,p.in_stock,p.unit,p.qty_min,p.qty_step,p.params,c.title AS category_title')
                ->where('p.id=' . $id);
            return $db->setQuery($query, 0, 1)->loadObject();
        };
        $source = $load($sourceId);
        if (!$source || (int) $source->group_id < 1) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404); }
        $services = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
        $this->groupListing()->attach([$source], $candidates, $services->orderRules());
        if (!GroupCardRenderer::isGroup($source)) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404); }
        $values = $source->group_selection;
        $fields = $source->listing_group->choice_fields ?? [];
        if (!$values || count($values) !== count($fields)) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404); }
        foreach ($changes as $fieldId => $optionId) {
            $fieldId = (int) $fieldId;
            $field = $fields[$fieldId] ?? null;
            $display = $field ? (string) $field->params->get($field->type === 'color' ? 'color_group_card_display' : 'group_card_display', 'off') : 'off';
            if (!$field || !$field->params->get('variation', 0) || $display === 'off' || !is_scalar($optionId)
                || !in_array((int) $optionId, array_map(static fn(object $v): int => $v->id, $field->values), true)) {
                throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404);
            }
            $values[$fieldId] = (int) $optionId;
        }
        $visible = $preferred = [];
        foreach ($fields as $fieldId => $field) {
            $display = (string) $field->params->get($field->type === 'color' ? 'color_group_card_display' : 'group_card_display', 'off');
            if (!$field->params->get('variation', 0) || $display === 'off') { $preferred[$fieldId] = $values[$fieldId]; }
            else { $visible[$fieldId] = $values[$fieldId]; }
        }
        $id = (new VariantSelection($db))->choose($candidates, (int) $source->group_id, $visible, $preferred);
        $item = $id ? $load($id) : null;
        if (!$item || (int) $item->group_id !== (int) $source->group_id) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_UNAVAILABLE', 404); }
        $item->params = new Registry($item->params);
        $item->price_free = $services->orderRules()->priceIsFree($item);
        // Reuse the same group's scope/options, and only replace its selected member.
        $item->listing_type = 'group';
        $item->listing_group = $source->listing_group;
        $item->group_selection = $services->groups()->combination($id, (int) $source->group_id);
        $this->attachCardData([$item], $imagesLimit);
        return $item;
    }

    /**
     * Столбец и направление сортировки (столбец — из закрытого списка, в запрос попадает через quoteName).
     *
     * @return  array{0: string, 1: string}
     */
    protected function sortExpression(string $ordering): array
    {
        // Релевантность есть только у поиска; без запроса вместо неё — обычный порядок.
        if ($ordering === 'relevance') {
            return $this->getQueryText() !== '' ? ['m.score', 'DESC'] : $this->sortExpression('order');
        }

        $category = (int) $this->getParent()->id;
        $column = $category ? match ($ordering) {
            'price_asc', 'price_desc' => 'pc.price_sort',
            'new' => 'pc.created',
            'title' => 'pc.title_sort',
            default => 'pc.sort_order',
        } : match ($ordering) {
            'price_asc', 'price_desc' => 'p.price_min',
            'new' => 'p.created',
            'title' => 'p.title',
            default => 'p.id',
        };

        return [$column, in_array($ordering, ['price_desc', 'new'], true) ? 'DESC' : 'ASC'];
    }

    /** SQL from a closed list; a group's representative is its first matching member under this order. */
    private function groupSortExpression(string $ordering): string
    {
        return match ($ordering) {
            'title' => 'COALESCE(lg.title,p.title)',
            'price_asc' => 'p.price_min',
            'price_desc' => 'p.price_max',
            'new' => 'p.created',
            'relevance' => $this->getQueryText() !== '' ? 'm.score' : ((int) $this->getParent()->id > 0 ? 'COALESCE(pc.sort_order,p.id)' : 'p.id'),
            default => (int) $this->getParent()->id > 0 ? 'COALESCE(pc.sort_order,p.id)' : 'p.id',
        };
    }

    /** Итоги всей категории (по индексу, с потолком); цены нужны только если включены настройками. Корень цен не имеет. */
    public function getProductStats(bool $prices): object
    {
        $category = (int) $this->getParent()->id;
        if ($this->groupsEnabled()) {
            $query = $this->productsQuery(forListing: true)->select('MIN(p.price_min) AS price_min,MAX(p.price_max) AS price_max');
            $range = $prices ? $this->getDatabase()->setQuery($query)->loadObject() : null;
            $count = $this->getTotal();
            return (object) ['product_count' => (string) $count . ($this->totalCapped ? '+' : ''), 'price_min' => $range->price_min ?? null, 'price_max' => $range->price_max ?? null];
        }
        $range = $prices ? $this->getPriceRange($category) : ['min' => null, 'max' => null];

        return (object) ['product_count' => $this->getCountLabel($category), 'price_min' => $range['min'], 'price_max' => $range['max']];
    }

    protected function productsQuery(?FilterState $filterOverride = null, bool $forListing = false, array $knownGroups = []): QueryInterface
    {
        $category = (int) $this->getParent()->id;
        $grouped = $forListing && $this->groupsEnabled();
        $db = $this->getDatabase();
        $levels = implode(',', array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels()) ?: [0]);
        $orderedScan = !$grouped && $category > 0 && $this->indexHint !== null;
        $query = $db->createQuery();
        if ($orderedScan) {
            $query->from($db->quoteName('#__dharmashop_product_categories', 'pc') . ' FORCE INDEX (' . $this->indexHint . ')')
                ->join('INNER', $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id=pc.product_id');
        } else { $query->from($db->quoteName('#__dharmashop_products', 'p')); }
        $query->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where('p.state = 1')->where('c.extension = ' . $db->quote('com_dharmashop'))
            ->where('c.published = 1')->where('c.access IN (' . $levels . ')');
        if ($category > 0) {
            if ($grouped) {
                $query->join('LEFT', $db->quoteName('#__dharmashop_product_categories', 'pc') . ' ON pc.product_id=p.id AND pc.catid=:category AND pc.state=1')
                    ->bind(':category', $category, ParameterType::INTEGER);
            } else {
                if (!$orderedScan) { $query->join('INNER', $db->quoteName('#__dharmashop_product_categories', 'pc') . ' ON pc.product_id = p.id'); }
                $query->where('pc.catid = :category')->where('pc.state = 1')->bind(':category', $category, ParameterType::INTEGER);
            }
        }
        // Закрытый или снятый предок основной категории также скрывает товар.
        $blocked = $db->createQuery()->select('blocked.id')->from($db->quoteName('#__categories', 'blocked'))
            ->where('blocked.extension = ' . $db->quote('com_dharmashop'))
            ->where('blocked.lft <= c.lft')->where('blocked.rgt >= c.rgt')
            ->where('(blocked.published != 1 OR blocked.access NOT IN (' . $levels . '))');
        if (!$this->baseFacetsAccessible()) { $query->where('NOT EXISTS (' . $blocked . ')'); }
        if ($grouped) {
            $this->groupListing()->applyContext($query, $knownGroups ? 0 : $category, $this->taxonomy()->extension, $this->getCurrentUser()->getAuthorisedViewLevels());
            if ($knownGroups) { $query->whereIn('p.group_id', $knownGroups); }
        }

        // Фильтр: условия выбора посетителя (поля, цена, бренд, бейджи, наличие) поверх состава списка.
        $filterState = $filterOverride ?? $this->getFilterState();

        if (!$filterState->isEmpty()) {
            (new \Dharma\Component\DharmaShop\Administrator\Filter\FilterService($this->getDatabase()))->query()
                ->apply($query, $filterState, $this->getFilterDefinition(), $category > 0 && !$grouped);
        }

        // Поиск на странице списка (категория, бренд, бейдж, каталог): состав ограничивает поисковый индекс.
        if (($text = $this->getQueryText()) !== '') {
            $matched = ProductSearch::matched($text, $this->searchIsFuzzy($text));

            if ($matched === null) {
                return $query->where('1 = 0');
            }

            [$sql, $this->searchBinds] = $matched;
            $query->join('INNER', '(' . $sql . ') AS m ON m.product_id = p.id');

            foreach ($this->searchBinds as $name => $value) {
                $query->bind($name, $this->searchBinds[$name], ParameterType::STRING);
            }
        }

        return $query;
    }
}
