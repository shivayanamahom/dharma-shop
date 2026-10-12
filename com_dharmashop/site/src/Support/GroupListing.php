<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Order\OrderRules;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Mixed listing units. Candidate queries already contain all product ACL, filter and search conditions. */
final class GroupListing
{
    private array $bindings = [];
    private int $sequence = 0;
    private array $access = [0];

    public function __construct(private readonly DatabaseInterface $db) {}

    /** Positive product ID / negative group ID: two disjoint namespaces for COUNT DISTINCT. */
    public static function unit(): string
    {
        return 'CASE WHEN p.group_id>0 THEN -CAST(p.group_id AS SIGNED) ELSE p.id END';
    }

    /** Group taxonomy decides where the group appears; a visible member is still required. */
    public function applyContext(QueryInterface $query, int $category, string $extension, array $access): void
    {
        $this->access = array_map('intval', $access) ?: [0];
        $levels = implode(',', $this->access);
        $catalog = $this->db->quote('com_dharmashop');
        $query->join('LEFT', '#__dharmashop_product_groups AS lg ON lg.id=p.group_id')
            ->join('LEFT', '#__categories AS lgc ON lgc.id=lg.catid');
        $ungrouped = '(p.group_id IS NULL OR p.group_id=0)';
        $visible = "lg.state=1 AND ((lg.catid=1 AND lgc.extension='system' AND lgc.parent_id=0) OR (lgc.extension=$catalog AND lgc.published=1 AND lgc.access IN ($levels)"
            . " AND NOT EXISTS (SELECT 1 FROM #__categories lgb WHERE lgb.extension=$catalog AND lgb.lft<=lgc.lft AND lgb.rgt>=lgc.rgt AND (lgb.published<>1 OR lgb.access NOT IN ($levels)))))";
        $query->where("($ungrouped OR ($visible))");
        if ($category > 0) {
            // Probe only groups of matching products. Materialising every member of the branch
            // before applying selective filters made a 100k category unnecessarily expensive.
            $members = 'SELECT 1 FROM #__dharmashop_group_categories lgt INNER JOIN #__categories lga ON lga.id=lgt.catid'
                . ' INNER JOIN #__categories lgs ON lgs.id=' . $category
                . ' WHERE lgt.group_id=p.group_id AND lga.extension=' . $this->db->quote($extension)
                . " AND lga.lft>=lgs.lft AND lga.rgt<=lgs.rgt AND lga.published=1 AND lga.access IN ($levels)"
                . ' AND NOT EXISTS (SELECT 1 FROM #__categories lgh WHERE lgh.extension=lga.extension AND lgh.lft<=lga.lft AND lgh.rgt>=lga.rgt'
                . " AND (lgh.published<>1 OR lgh.access NOT IN ($levels)))";
            $query->where("(($ungrouped AND pc.product_id IS NOT NULL) OR (p.group_id>0 AND EXISTS ($members)))");
        }
    }

    /** Keep the first matching member under this sort, BEFORE LIMIT/OFFSET. */
    public function representatives(QueryInterface $candidates, string $sort, string $direction, int $category = 0): QueryInterface
    {
        $query = $this->db->createQuery();
        $ranked = clone $candidates;
        // Rank only narrow matching rows once. Repeating a correlated full category scope per member
        // was much slower for large groups/catalogues and unstable across MySQL query plans.
        $ranked->select(['p.id', 'ROW_NUMBER() OVER (PARTITION BY ' . self::unit() . ' ORDER BY ' . $sort . ' ' . $direction . ',p.id ' . $direction . ') AS listing_rank']);
        $sql = $this->embed($ranked, $query);
        return $query->from('(' . $sql . ') AS listing_ranked')
            ->join('INNER', '#__dharmashop_products p ON p.id=listing_ranked.id')
            ->join('INNER', '#__categories c ON c.id=p.catid')
            ->join('LEFT', '#__dharmashop_product_groups lg ON lg.id=p.group_id')
            ->join('LEFT', '#__dharmashop_product_categories pc ON pc.product_id=p.id AND pc.catid=' . $category . ' AND pc.state=1')
            ->where('listing_ranked.listing_rank=1');
    }

    /** Bounded batch queries for groups on this page; no per-card SQL or global group price caches. */
    public function attach(array $items, QueryInterface $candidates, OrderRules $rules): void
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn(object $p): int => (int) ($p->group_id ?? 0), $items))));
        if (!$ids) { return; }
        $groups = $this->db->setQuery($this->db->createQuery()->select('id,title,introtext,cover,cover_alt')
            ->from('#__dharmashop_product_groups')->whereIn('id', $ids))->loadObjectList('id');
        $matched = clone $candidates;
        $matched->whereIn('p.group_id', $ids);
        $statsQuery = clone $matched;
        $statsQuery->select('p.group_id,COUNT(*) AS product_count,MIN(p.price_min) AS price_min,MAX(p.price_max) AS price_max')->group('p.group_id');
        foreach ($this->db->setQuery($statsQuery)->loadObjectList() as $stats) {
            if (!isset($groups[(int) $stats->group_id])) { continue; }
            $group = $groups[(int) $stats->group_id];
            $group->product_count = (int) $stats->product_count;
            $group->price_min = $stats->price_min;
            $group->price_max = $stats->price_max;
            $group->price_free = false;
            $group->choice_fields = [];
        }
        $zero = clone $matched;
        $zero->where('p.price_min=0')->select('p.group_id,p.catid,p.price_zero_free');
        foreach ($this->db->setQuery($zero)->loadObjectList() as $product) {
            if (isset($groups[(int) $product->group_id]) && $rules->priceIsFree($product)) { $groups[(int) $product->group_id]->price_free = true; }
        }
        $memberIds = clone $matched;
        $memberIds->select('p.id,p.group_id');
        $choices = $this->db->createQuery();
        $sql = $this->embed($memberIds, $choices);
        $choices->select('DISTINCT eligible.group_id,f.id AS field_id,f.title AS field_title,f.type,f.params,ga.ordering,o.id AS option_id,o.title,o.color,o.image,o.ordering AS option_order')
            ->from('(' . $sql . ') AS eligible')
            ->join('INNER', '#__dharmashop_group_axes ga ON ga.group_id=eligible.group_id')
            ->join('INNER', '#__dharmashop_fields f ON f.id=ga.field_id AND f.state=1')
            ->join('LEFT', '#__dharmashop_fieldgroups fg ON fg.id=f.group_id')
            ->where('f.access IN (' . implode(',', $this->access) . ')')->where('(f.group_id=0 OR (fg.state=1 AND fg.target=f.target))')
            ->join('INNER', '#__dharmashop_field_values fv ON fv.product_id=eligible.id AND fv.field_id=f.id AND fv.variant_id IS NULL')
            ->join('INNER', '#__dharmashop_field_options o ON o.id=fv.option_id AND o.field_id=f.id')
            ->order('eligible.group_id,ga.ordering,f.id,o.ordering,o.id');
        foreach ($this->db->setQuery($choices)->loadObjectList() as $row) {
            if (!isset($groups[(int) $row->group_id])) { continue; }
            $group = $groups[(int) $row->group_id];
            $group->choice_fields[(int) $row->field_id] ??= (object) ['id' => (int) $row->field_id, 'title' => $row->field_title, 'type' => $row->type, 'params' => new Registry($row->params), 'values' => []];
            $group->choice_fields[(int) $row->field_id]->values[] = (object) ['id' => (int) $row->option_id, 'title' => $row->title, 'color' => $row->color, 'image' => $row->image];
        }
        $selected = [];
        if (ComponentHelper::getParams('com_dharmashop')->get('group_card_mode', 'simple') === 'ajax') {
            $productIds = array_map(static fn(object $p): int => (int) $p->id, $items);
            $query = $this->db->createQuery()->select('fv.product_id,fv.field_id,fv.option_id')
                ->from('#__dharmashop_field_values fv')->join('INNER', '#__dharmashop_products sp ON sp.id=fv.product_id')
                ->join('INNER', '#__dharmashop_group_axes sa ON sa.group_id=sp.group_id AND sa.field_id=fv.field_id')
                ->whereIn('fv.product_id', $productIds)->where('fv.variant_id IS NULL');
            foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
                $selected[(int) $row->product_id][(int) $row->field_id] = (int) $row->option_id;
            }
        }
        foreach ($items as $item) {
            if (isset($groups[(int) ($item->group_id ?? 0)])) {
                $item->listing_type = 'group';
                $item->listing_group = $groups[(int) $item->group_id];
                $item->group_selection = $selected[(int) $item->id] ?? [];
            }
        }
    }

    /** Inherit the selected representatives' images in one batch, after ordinary card enrichment. */
    public function inheritImages(array $items, int $imagesLimit): void
    {
        $groupIds = [];
        foreach ($items as $item) {
            if (($item->inherit_images ?? 'own') === 'group' && isset($item->listing_group)) { $groupIds[] = (int) $item->group_id; }
        }
        $media = [];
        if ($groupIds && $imagesLimit !== 1) {
            $limit = $imagesLimit > 0 ? min(20, $imagesLimit) : 20;
            foreach ($this->db->setQuery($this->db->createQuery()->select('group_id,path,alt')->from('#__dharmashop_group_media')
                ->whereIn('group_id', array_values(array_unique($groupIds)))->order('group_id,ordering'))->loadObjectList() as $row) {
                if (count($media[(int) $row->group_id] ?? []) < $limit) { $media[(int) $row->group_id][] = $row; }
            }
        }
        foreach ($items as $item) {
            if (!isset($item->listing_group)) { continue; }
            if (($item->inherit_images ?? 'own') === 'group') {
                $item->cover = $item->listing_group->cover;
                $item->cover_alt = $item->listing_group->cover_alt;
                $item->images = $media[(int) $item->group_id] ?? [];
            } elseif (($item->inherit_images ?? 'own') === 'empty') {
                $item->cover = $item->cover_alt = '';
                $item->images = [];
            }
        }
    }

    /** Derived SQL loses bindings: copy them under fresh names, retaining mutable references. */
    private function embed(QueryInterface $inner, QueryInterface $outer): string
    {
        $names = [];
        foreach ($inner->getBounded() as $name => $bound) {
            $key = ':listing_' . ++$this->sequence;
            $names[$name] = $key;
            $this->bindings[$key] = $bound->value;
            $outer->bind($key, $this->bindings[$key], $bound->dataType);
        }
        return strtr((string) $inner, $names);
    }
}
