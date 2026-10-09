<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Direct group bindings, independent of the product listing index and member bindings. */
final class GroupTaxonomy
{
    public function __construct(private readonly DatabaseInterface $db) {}

    public function get(int $id): array
    {
        $group = $id > 0 ? $this->db->setQuery('SELECT catid,brand_id FROM #__dharmashop_product_groups WHERE id=' . $id)->loadObject() : null;
        $result = ['catid' => (int) ($group->catid ?? 0), 'extra_catids' => [], 'brand_id' => (int) ($group->brand_id ?? 0), 'badge_ids' => []];
        if (!$group) { return $result; }
        $rows = $this->db->setQuery('SELECT gc.catid,c.extension FROM #__dharmashop_group_categories gc INNER JOIN #__categories c ON c.id=gc.catid WHERE gc.group_id=' . $id . ' AND gc.is_main=0 ORDER BY c.lft,c.id')->loadObjectList();
        foreach ($rows as $row) {
            if ($row->extension === CategoryTypes::CATEGORY) { $result['extra_catids'][] = (int) $row->catid; }
            if ($row->extension === CategoryTypes::BADGE) { $result['badge_ids'][] = (int) $row->catid; }
        }
        return $result;
    }

    /** Validate existence and extension in one query, before any group write. */
    public function prepare(array $data): array
    {
        $main = (int) ($data['catid'] ?? 0);
        $brand = (int) ($data['brand_id'] ?? 0);
        if ($main < 1) { throw new \RuntimeException('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'); }
        $ids = static fn(array $values): array => array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0)));
        $extra = array_values(array_diff($ids((array) ($data['extra_catids'] ?? [])), [$main]));
        $badges = $ids((array) ($data['badge_ids'] ?? []));
        $wanted = [$main => $main === 1 ? 'system' : CategoryTypes::CATEGORY];
        foreach ($extra as $id) { $wanted[$id] = CategoryTypes::CATEGORY; }
        foreach ($badges as $id) {
            if (isset($wanted[$id])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_TAXONOMY_INVALID'); }
            $wanted[$id] = CategoryTypes::BADGE;
        }
        if ($brand !== 0) {
            if ($brand < 1 || isset($wanted[$brand])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_TAXONOMY_INVALID'); }
            $wanted[$brand] = CategoryTypes::BRAND;
        }
        $actual = $this->db->setQuery('SELECT id,extension FROM #__categories WHERE id IN (' . implode(',', array_keys($wanted)) . ') AND (id<>1 OR (parent_id=0 AND level=0))')->loadAssocList('id', 'extension');
        foreach ($wanted as $id => $extension) {
            if (($actual[$id] ?? null) !== $extension) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_TAXONOMY_INVALID'); }
        }
        return ['catid' => $main, 'extra_catids' => $extra, 'brand_id' => $brand, 'badge_ids' => $badges];
    }

    /** Called inside the group save transaction, with a prepared taxonomy. */
    public function store(int $id, array $taxonomy): void
    {
        $this->db->setQuery('DELETE FROM #__dharmashop_group_categories WHERE group_id=' . $id)->execute();
        $direct = array_values(array_unique(array_filter(array_merge([$taxonomy['catid'], $taxonomy['brand_id']], $taxonomy['extra_catids'], $taxonomy['badge_ids']))));
        foreach ($direct as $catid) {
            $record = (object) ['group_id' => $id, 'catid' => $catid, 'is_main' => (int) ($catid === $taxonomy['catid'])];
            $this->db->insertObject('#__dharmashop_group_categories', $record);
        }
    }
}
