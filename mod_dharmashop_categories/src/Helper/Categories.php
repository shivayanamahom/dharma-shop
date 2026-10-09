<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopCategories\Site\Helper;

use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Limited category selection with optional indexed statistics; never loads product cards or custom fields. */
final class Categories
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /** @return array{items: array, more: bool, routes: array} */
    public function find(Registry $params, array $levels): array
    {
        $db = $this->db;
        $limit = max(1, min(100, (int) $params->get('limit', 6)));
        $levels = self::ids($levels);
        if (!$levels) { return ['items' => [], 'more' => false, 'routes' => []]; }
        $access = implode(',', $levels);
        $query = $db->createQuery()
            ->select('c.id, c.parent_id, c.title, c.alias, c.params, c.lft, c.rgt')
            ->from($db->quoteName('#__categories', 'c'))
            ->where('c.extension = ' . $db->quote('com_dharmashop'))
            ->where('c.id > 1 AND c.published = 1 AND c.access IN (' . $access . ')');
        if ($params->get('mode', 'children') === 'selected') {
            $ids = self::ids($params->get('selected', []));
            if (!$ids) { return ['items' => [], 'more' => false, 'routes' => []]; }
            $query->where('c.id IN (' . implode(',', $ids) . ')');
        } else {
            $query->where('c.parent_id = ' . max(1, (int) $params->get('parent', 1)));
            $excluded = self::ids($params->get('excluded', []));
            if ($excluded) { $query->where('c.id NOT IN (' . implode(',', $excluded) . ')'); }
        }
        // A published child must not expose a hidden or inaccessible ancestor.
        $ancestors = $db->createQuery()->select('1')->from($db->quoteName('#__categories', 'a'))
            ->where('a.extension = ' . $db->quote('com_dharmashop'))
            ->where('a.lft < c.lft AND a.rgt > c.rgt')
            ->where('(a.published <> 1 OR a.access NOT IN (' . $access . '))');
        $query->where('NOT EXISTS (' . $ancestors . ')')->order('c.lft ASC');
        $items = $db->setQuery($query, 0, $limit + 1)->loadObjectList();
        $more = count($items) > $limit;
        $items = array_slice($items, 0, $limit);
        if (!$items) { return ['items' => [], 'more' => false, 'routes' => []]; }

        // Root cards already contain all route data. Deeper selections load missing ancestors in one batch.
        $known = array_fill_keys(array_map(static fn (object $item): int => (int) $item->id, $items), true);
        $parents = [];
        foreach ($items as $item) {
            if ((int) $item->parent_id > 1 && !isset($known[(int) $item->parent_id])) {
                $parents[(int) $item->parent_id] = (int) $item->parent_id;
            }
        }
        $routes = $items;
        if ($parents) {
            $routes = array_merge($routes, $db->setQuery($db->createQuery()->select('DISTINCT a.id, a.parent_id, a.alias')
                ->from($db->quoteName('#__categories', 'a'))
                ->innerJoin($db->quoteName('#__categories', 'c') . ' ON a.lft <= c.lft AND a.rgt >= c.rgt')
                ->where('c.id IN (' . implode(',', $parents) . ')')->where('a.extension = ' . $db->quote('com_dharmashop')))->loadObjectList());
        }
        foreach ($items as $item) { $item->params = new Registry($item->params); }
        return ['items' => $items, 'more' => $more, 'routes' => $routes];
    }

    private static function ids(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $values), static fn (int $id): bool => $id > 0)));
    }

    /** One bounded query for direct children of all displayed accordion headings. */
    public function children(array $parents, Registry $params, array $levels): array
    {
        if (!$parents || !($levels = self::ids($levels))) { return []; }
        $db = $this->db;
        $limit = max(1, min(100, (int) $params->get('child_limit', 12)));
        $access = implode(',', $levels);
        $excluded = $params->get('mode', 'children') === 'children' ? self::ids($params->get('excluded', [])) : [];
        $parts = [];
        foreach ($parents as $parent) {
            // The parent and its ancestors were already checked in find(). Only the child's own access remains.
            $query = $db->createQuery()->select('id, parent_id, title, alias, params')->from($db->quoteName('#__categories'))
                ->where('extension = ' . $db->quote('com_dharmashop'))
                ->where('parent_id = ' . (int) $parent->id)->where('published = 1 AND access IN (' . $access . ')')
                ->order('lft ASC')->setLimit($limit + 1);
            if ($excluded) { $query->where('id NOT IN (' . implode(',', $excluded) . ')'); }
            $parts[] = '(' . $query . ')';
        }
        $groups = [];
        foreach ($db->setQuery(implode(' UNION ALL ', $parts))->loadObjectList() as $item) {
            $item->params = new Registry($item->params);
            $groups[(int) $item->parent_id][] = $item;
        }
        $result = [];
        foreach ($parents as $parent) {
            $items = $groups[(int) $parent->id] ?? [];
            $result[(int) $parent->id] = ['items' => array_slice($items, 0, $limit), 'more' => count($items) > $limit];
        }
        return $result;
    }

    /** A single round trip; counts stop at 10,001 rows, price bounds use the ordered price index. */
    public function stats(array $items, bool $count, bool $prices): array
    {
        if ((!$count && !$prices) || !$items) { return []; }
        $parts = [];
        foreach ($items as $item) {
            $id = (int) $item->id;
            $where = ' WHERE catid = ' . $id . ' AND state = 1';
            $parts[] = 'SELECT ' . $id . ' AS id, '
                . ($count ? '(SELECT COUNT(*) FROM (SELECT 1 FROM #__dharmashop_product_categories' . $where . ' LIMIT 10001) AS capped)' : 'NULL') . ' AS total, '
                . ($prices ? '(SELECT MIN(price_sort) FROM #__dharmashop_product_categories FORCE INDEX (idx_l_price)' . $where . ')' : 'NULL') . ' AS pmin, '
                . ($prices ? '(SELECT MAX(price_sort) FROM #__dharmashop_product_categories FORCE INDEX (idx_l_price)' . $where . ')' : 'NULL') . ' AS pmax';
        }
        return $this->db->setQuery(implode(' UNION ALL ', $parts))->loadObjectList('id');
    }
}
