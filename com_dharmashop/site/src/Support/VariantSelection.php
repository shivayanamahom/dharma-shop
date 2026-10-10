<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Visible choices constrain the member; hidden choices only rank matching members. */
final class VariantSelection
{
    public function __construct(private readonly DatabaseInterface $db) {}

    public function choose(QueryInterface $candidates, int $groupId, array $visible, array $preferred): int
    {
        if (!$visible || count($visible) + count($preferred) > 6) { return 0; }
        $service = new GroupService($this->db);
        $exact = $service->resolve($groupId, $visible + $preferred);
        if ($exact) {
            $query = clone $candidates;
            $query->clear('select')->clear('order')->select('p.id')->where('p.id=' . $exact)->where('p.group_id=' . $groupId);
            if ($this->db->setQuery($query, 0, 1)->loadResult()) { return $exact; }
        }
        if (!$preferred) { return 0; }
        $query = clone $candidates;
        $query->clear('select')->clear('order')->select('p.id')->where('p.group_id=' . $groupId);
        $match = static fn(int $field, int $option): string => 'EXISTS (SELECT 1 FROM #__dharmashop_field_values vs WHERE vs.product_id=p.id AND vs.variant_id IS NULL AND vs.field_id=' . $field . ' AND vs.option_id=' . $option . ')';
        foreach ($visible as $field => $option) { $query->where($match((int) $field, (int) $option)); }
        $scores = [];
        foreach ($preferred as $field => $option) { $scores[] = '(' . $match((int) $field, (int) $option) . ')'; }
        $query->order('(' . implode('+', $scores) . ') DESC')->order('p.id ASC');
        $id = (int) $this->db->setQuery($query, 0, 1)->loadResult();
        if (!$id) { return 0; }
        // Validate the complete indexed combination after the bounded partial lookup.
        return $service->resolve($groupId, $service->combination($id, $groupId)) === $id ? $id : 0;
    }
}
