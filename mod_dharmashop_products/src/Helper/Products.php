<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopProducts\Site\Helper;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinitionLoader;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterField;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterQuery;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterState;
use Dharma\Component\DharmaShop\Site\Support\GroupListing;
use Dharma\Component\DharmaShop\Site\Support\ProductFields;
use Dharma\Component\DharmaShop\Site\Support\ProductModuleRegistry;
use Dharma\Component\DharmaShop\Site\Support\RecentProducts;
use Dharma\Component\DharmaShop\Site\Support\SaleCountdown;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Bounded storefront query for one product-module instance. */
final class Products
{
    public function __construct(private readonly DatabaseInterface $db, private readonly CMSApplicationInterface $app) {}

    /** @return array<int, object> */
    public function find(Registry $params): array
    {
        $mode = in_array((string) $params->get('source', 'new'), ['new', 'recent'], true) ? (string) $params->get('source', 'new') : 'new';
        $grouped = (string) $params->get('listing_mode', 'products') === 'meta';
        $limit = max(1, min(100, (int) $params->get('limit', 6)));
        $levels = FilterDefinitionLoader::currentLevels();
        $recent = $mode === 'recent' ? RecentProducts::read($this->app) : [];
        $currentProduct = $this->app->getInput()->getCmd('option') === 'com_dharmashop'
            && $this->app->getInput()->getCmd('view') === 'product' ? $this->app->getInput()->getInt('id') : 0;
        $recent = array_values(array_filter($recent, static fn(int $id): bool => $id !== $currentProduct));
        if ($mode === 'recent' && !$recent) { return []; }

        $query = $this->candidates($params, $levels, $mode, $recent);
        $direction = $mode === 'recent' ? 'ASC' : ((string) $params->get('sort_direction', 'DESC') === 'ASC' ? 'ASC' : 'DESC');
        $sort = match ((string) $params->get('sort', 'created')) {
            'price' => 'p.price_min', 'title' => 'p.title', default => 'p.created',
        };
        if ($mode === 'recent') { $sort = 'FIELD(p.id,' . implode(',', array_map('intval', $recent)) . ')'; }

        if ($grouped) {
            $listing = new GroupListing($this->db);
            $listing->applyContext($query, 0, 'com_dharmashop', $levels);
            $result = $listing->representatives($query, $sort, $direction);
            $this->selectProducts($result);
            $result->order('listing_ranked.listing_sort ' . $direction)->order('p.id ASC');
            $items = $this->db->setQuery($result, 0, $limit)->loadObjectList();
            $this->prepareItems($items);
            $component = $this->app->bootComponent('com_dharmashop');
            $listing->attach($items, $query, $component->getServices()->orderRules());
            $listing->inheritImages($items, 1);
        } else {
            $this->selectProducts($query);
            $query->order($sort . ' ' . $direction)->order('p.id DESC');
            $items = $this->db->setQuery($query, 0, $limit)->loadObjectList();
            $this->prepareItems($items);
            $this->attachCardData($items, $levels);
        }

        if ((int) $params->get('exclude_duplicates', 0) === 1) { ProductModuleRegistry::claim($items); }
        return $items;
    }

    private function candidates(Registry $params, array $levels, string $mode, array $recent): QueryInterface
    {
        $levelsSql = implode(',', array_map('intval', $levels) ?: [1]);
        $query = $this->db->createQuery()->from($this->db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id=p.catid')
            ->where('p.state=1')->where('c.extension=' . $this->db->quote('com_dharmashop'))
            ->where('c.published=1')->where('c.access IN (' . $levelsSql . ')');
        $blocked = $this->db->createQuery()->select('bc.id')->from($this->db->quoteName('#__categories', 'bc'))
            ->where('bc.extension=' . $this->db->quote('com_dharmashop'))->where('bc.lft<=c.lft')->where('bc.rgt>=c.rgt')
            ->where('(bc.published<>1 OR bc.access NOT IN (' . $levelsSql . '))');
        $query->where('NOT EXISTS (' . $blocked . ')');

        if ($mode === 'recent') {
            $query->whereIn('p.id', array_map('intval', $recent));
        }
        $definition = (new FilterDefinitionLoader($this->db))->forCategory(0, $levels);
        $state = $this->filterState($params, $definition, $levelsSql);
        if (!$state->isEmpty()) {
            $conditions = $this->conditionGroups($state, $definition);
            if ($conditions) {
                $logic = (string) $params->get('criteria_logic', 'and') === 'or' ? ' OR ' : ' AND ';
                $query->where('(' . implode($logic, $conditions) . ')');
            }
        }

        $excludeProducts = (int) $params->get('exclude_duplicates', 0) === 1 ? ProductModuleRegistry::products() : [];
        $excludeGroups = (int) $params->get('exclude_duplicates', 0) === 1 ? ProductModuleRegistry::groups() : [];
        if ($excludeProducts) { $query->where('p.id NOT IN (' . implode(',', $excludeProducts) . ')'); }
        if ($excludeGroups) { $query->where('(p.group_id IS NULL OR p.group_id=0 OR p.group_id NOT IN (' . implode(',', $excludeGroups) . '))'); }
        return $query;
    }

    /** Preserve each category/brand/badge/field as one group when the module combines groups with OR. */
    private function conditionGroups(FilterState $state, object $definition): array
    {
        $filter = new FilterQuery($this->db);
        $groups = [];
        foreach ([['brands', $state->brands], ['badges', $state->badges], ['categories', $state->categories]] as [$key, $ids]) {
            if (!$ids) { continue; }
            $single = match ($key) {
                'brands' => new FilterState(brands: $ids),
                'badges' => new FilterState(badges: $ids),
                default => new FilterState(categories: $ids),
            };
            $parts = $filter->conditions($single, $definition, false);
            if ($parts) { $groups[] = '(' . implode(' AND ', $parts) . ')'; }
        }
        foreach ($state->options as $fieldId => $ids) {
            $parts = $filter->conditions(new FilterState(options: [(int) $fieldId => $ids]), $definition, false);
            if ($parts) { $groups[] = '(' . implode(' AND ', $parts) . ')'; }
        }
        foreach ($state->ranges as $fieldId => $range) {
            $parts = $filter->conditions(new FilterState(ranges: [(int) $fieldId => $range]), $definition, false);
            if ($parts) { $groups[] = '(' . implode(' AND ', $parts) . ')'; }
        }
        return $groups;
    }

    private function filterState(Registry $params, object $definition, string $levelsSql): FilterState
    {
        $categories = $this->allowedCategoryIds((array) $params->get('categories', []), $levelsSql);
        $brands = array_values(array_intersect(array_map('intval', (array) $params->get('brands', [])), array_map('intval', array_keys($definition->brands))));
        $badges = array_values(array_intersect(array_map('intval', (array) $params->get('badges', [])), array_map('intval', array_keys($definition->badges))));
        $options = [];
        foreach ((array) $params->get('field_values', []) as $token) {
            if (!is_string($token) || !preg_match('/^([a-zA-Z0-9_-]{1,190}):([a-zA-Z0-9_-]{1,190})$/D', $token, $match)) { continue; }
            $field = $definition->fieldByAlias($match[1]);
            if (!$field || $field->kind !== FilterField::OPTION || !($option = $field->option($match[2]))) { continue; }
            $options[$field->id][$option->id] = (int) $option->id;
        }
        foreach ($options as &$ids) { $ids = array_values($ids); } unset($ids);
        $ranges = [];
        foreach (array_slice((array) $params->get('field_ranges', []), 0, 12) as $row) {
            $row = (array) $row;
            $field = $definition->fieldByAlias((string) ($row['field'] ?? ''));
            if (!$field || $field->kind === FilterField::OPTION) { continue; }
            $minimum = $this->numericBound($row['min'] ?? '');
            $maximum = $this->numericBound($row['max'] ?? '');
            if ($minimum === null && $maximum === null) { continue; }
            if ($minimum !== null && $maximum !== null && $minimum > $maximum) { [$minimum, $maximum] = [$maximum, $minimum]; }
            if (isset($ranges[$field->id])) {
                $previous = $ranges[$field->id];
                if ($minimum !== null) { $previous['min'] = $previous['min'] === null ? $minimum : max($previous['min'], $minimum); }
                if ($maximum !== null) { $previous['max'] = $previous['max'] === null ? $maximum : min($previous['max'], $maximum); }
                $ranges[$field->id] = $previous;
            } else {
                $ranges[$field->id] = ['min' => $minimum, 'max' => $maximum];
            }
        }
        return new FilterState($options, $ranges, null, $brands, $badges, $categories);
    }

    private function numericBound(mixed $value): ?float
    {
        if (!is_scalar($value)) { return null; }
        $value = str_replace([" ", "\u{00A0}", ','], ['', '', '.'], trim((string) $value));
        if ($value === '' || !is_numeric($value)) { return null; }
        $number = (float) $value;
        return is_finite($number) && abs($number) <= 1.0E12 ? $number : null;
    }

    private function allowedCategoryIds(array $ids, string $levelsSql): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 1))), 0, 100);
        if (!$ids) { return []; }
        $selected = $this->db->createQuery()->select('s.id')->from($this->db->quoteName('#__categories', 's'))
            ->whereIn('s.id', $ids)->where('s.extension=' . $this->db->quote('com_dharmashop'))->where('s.published=1')
            ->where('s.access IN (' . $levelsSql . ')');
        $blocked = $this->db->createQuery()->select('b.id')->from($this->db->quoteName('#__categories', 'b'))
            ->where('b.extension=' . $this->db->quote('com_dharmashop'))->where('b.lft<=c.lft')->where('b.rgt>=c.rgt')
            ->where('(b.published<>1 OR b.access NOT IN (' . $levelsSql . '))');
        $descendants = $this->db->createQuery()->select('1')->from('(' . $selected . ') selected_categories')
            ->join('INNER', $this->db->quoteName('#__categories', 's') . ' ON s.id=selected_categories.id')
            ->where('c.lft>=s.lft')->where('c.rgt<=s.rgt');
        return array_map('intval', $this->db->setQuery($this->db->createQuery()->select('c.id')->from($this->db->quoteName('#__categories', 'c'))
            ->where('c.extension=' . $this->db->quote('com_dharmashop'))->where('c.published=1')->where('c.access IN (' . $levelsSql . ')')
            ->where('EXISTS (' . $descendants . ')')->where('NOT EXISTS (' . $blocked . ')'))->loadColumn());
    }

    private function selectProducts(QueryInterface $query): void
    {
        $query->select($this->db->quoteName(['p.id','p.catid','p.title','p.alias','p.cover','p.cover_alt','p.introtext','p.group_id',
            'p.inherit_images','p.price_min','p.price_max','p.price_zero_free','p.in_stock','p.unit','p.qty_min','p.qty_step','p.created']))
            ->select($this->db->quoteName('c.title', 'category_title'));
    }

    private function prepareItems(array $items): void
    {
        $rules = $this->app->bootComponent('com_dharmashop')->getServices()->orderRules();
        foreach ($items as $item) {
            $item->params = new Registry();
            $item->price_free = $rules->priceIsFree($item);
            $item->badges = $item->badges ?? [];
        }
    }

    private function attachCardData(array $items, array $levels): void
    {
        if (!$items) { return; }
        ProductFields::attach($this->db, $items, 'card');
        $ids = implode(',', array_map(static fn(object $item): int => (int) $item->id, $items));
        $prices = [];
        foreach ($this->db->setQuery('SELECT v.product_id,v.sku,vp.price,vp.price_final,vp.discount_start,vp.discount_end FROM '
            . $this->db->quoteName('#__dharmashop_variants', 'v') . ' JOIN ' . $this->db->quoteName('#__dharmashop_variant_prices', 'vp')
            . ' ON vp.variant_id=v.id WHERE v.product_id IN (' . $ids . ') AND v.is_default=1 AND v.state=1 AND vp.price_list_id=1')->loadObjectList() as $row) {
            $prices[(int) $row->product_id] = $row;
        }
        $badges = [];
        foreach ($this->db->setQuery('SELECT pc.product_id,c.id,c.title,c.params FROM ' . $this->db->quoteName('#__dharmashop_product_categories', 'pc')
            . ' JOIN ' . $this->db->quoteName('#__categories', 'c') . ' ON c.id=pc.catid WHERE pc.product_id IN (' . $ids . ') AND pc.direct=1'
            . ' AND c.extension=' . $this->db->quote('com_dharmashop.badge') . ' AND c.published=1 AND c.access IN (' . implode(',', array_map('intval', $levels) ?: [1]) . ') ORDER BY c.lft')->loadObjectList() as $row) {
            $settings = new Registry($row->params);
            if ((int) $settings->get('badge_show_list', 1) !== 1) { continue; }
            $color = (string) $settings->get('badge_color', 'primary');
            $badges[(int) $row->product_id][] = (object) ['id' => (int) $row->id, 'title' => (string) $row->title,
                'color' => in_array($color, ['primary','secondary','success','danger','warning','info','light','dark'], true) ? $color : 'primary',
                'url' => \Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=badge&id=' . (int) $row->id, false)];
        }
        foreach ($items as $item) {
            $item->badges = $badges[(int) $item->id] ?? [];
            $item->images = [];
            $item->base_price = $item->discount_amount = null;
            $item->discount_percent = 0.0;
            $row = $prices[(int) $item->id] ?? null;
            $item->sku = (string) ($row->sku ?? '');
            $item->sale_end_timestamp = null;
            if ($row && $row->price !== null && $row->price_final !== null && (float) $row->price > 0 && (float) $row->price_final < (float) $row->price) {
                $item->base_price = (string) $row->price;
                $item->discount_amount = number_format((float) $row->price - (float) $row->price_final, 2, '.', '');
                $item->discount_percent = (1 - (float) $row->price_final / (float) $row->price) * 100;
                $item->sale_end_timestamp = SaleCountdown::endTimestamp($row->discount_start, $row->discount_end, true);
            }
        }
    }
}
