<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Группа связывает обычные товары; цена и склад принадлежат единственному SKU товара. */
final class GroupService
{
    public const MEMBER_LIMIT = 500;
    public function __construct(private readonly DatabaseInterface $db) {}

    public function get(int $id): ?object
    {
        return $id > 0 ? ($this->db->setQuery('SELECT id,title,alias,state,catid,brand_id,introtext,`fulltext`,cover,cover_alt,price_min,price_max,in_stock,members_count,revision FROM #__dharmashop_product_groups WHERE id=' . $id)->loadObject() ?: null) : null;
    }

    public function taxonomy(int $id): array
    {
        return (new GroupTaxonomy($this->db))->get($id);
    }

    public function axes(int $id): array
    {
        return $this->db->setQuery('SELECT f.id,f.title,f.type,f.params,f.access,f.state FROM #__dharmashop_group_axes a INNER JOIN #__dharmashop_fields f ON f.id=a.field_id WHERE a.group_id=' . $id . ' ORDER BY a.ordering,f.id')->loadObjectList();
    }

    public function members(int $id): array
    {
        $rows = $this->db->setQuery('SELECT id,title,group_id,combo_hash,inherit_intro,inherit_full,inherit_images FROM #__dharmashop_products WHERE group_id=' . $id . ' ORDER BY id', 0, self::MEMBER_LIMIT + 1)->loadObjectList();
        if (count($rows) > self::MEMBER_LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT'); }
        return $rows;
    }

    public static function hash(array $values): string
    {
        ksort($values, SORT_NUMERIC);
        $parts = [];
        foreach ($values as $field => $option) { $parts[] = (int) $field . ':' . (int) $option; }
        return hash('sha256', 'v1|' . implode('|', $parts));
    }

    /** Читает реальные значения, POST накладывается лишь на подготовленные провайдером поля. */
    public function combination(int $productId, int $groupId, array $prepared = []): array
    {
        $axes = $this->axes($groupId);
        if (!$axes) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_AXES_REQUIRED'); }
        $values = [];
        $rows = $productId > 0 ? $this->db->setQuery('SELECT field_id,option_id FROM #__dharmashop_field_values WHERE product_id=' . $productId . ' AND variant_id IS NULL AND field_id IN (' . implode(',', array_map(static fn($a) => (int) $a->id, $axes)) . ')')->loadObjectList() : [];
        foreach ($rows as $row) { $values[(int) $row->field_id][] = (int) $row->option_id; }
        $result = [];
        foreach ($axes as $axis) {
            $fieldId = (int) $axis->id;
            $options = isset($prepared[$fieldId]) ? array_map(static fn($r) => (int) ($r['option_id'] ?? 0), $prepared[$fieldId]['rows']) : ($values[$fieldId] ?? []);
            if (count($options) !== 1 || $options[0] < 1) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED'); }
            $result[$fieldId] = $options[0];
        }
        $valid = $this->db->setQuery('SELECT id,field_id FROM #__dharmashop_field_options WHERE id IN (' . implode(',', $result) . ')')->loadAssocList('id', 'field_id');
        foreach ($result as $field => $option) {
            if ((int) ($valid[$option] ?? 0) !== $field) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED'); }
        }
        return $result;
    }

    /** Bounded batch read used when linking many existing products. */
    private function combinations(array $ids, int $groupId): array
    {
        $axes = array_map(static fn($a) => (int) $a->id, $this->axes($groupId));
        if (!$axes) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_AXES_REQUIRED'); }
        $rows = $this->db->setQuery('SELECT fv.product_id,fv.field_id,fv.option_id FROM #__dharmashop_field_values fv INNER JOIN #__dharmashop_field_options o ON o.id=fv.option_id AND o.field_id=fv.field_id WHERE fv.product_id IN (' . implode(',', $ids) . ') AND fv.variant_id IS NULL AND fv.field_id IN (' . implode(',', $axes) . ')')->loadObjectList();
        $values = [];
        foreach ($rows as $row) { $values[(int)$row->product_id][(int)$row->field_id][] = (int)$row->option_id; }
        $result = [];
        foreach ($ids as $id) {
            foreach ($axes as $axis) {
                $options = $values[$id][$axis] ?? [];
                if (count($options) !== 1 || $options[0] < 1) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED'); }
                $result[$id][$axis] = $options[0];
            }
        }
        return $result;
    }

    public function validateProduct(int $productId, int $groupId, array $prepared = []): ?string
    {
        if ($groupId <= 0) { return null; }
        if (!$this->get($groupId)) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_NOT_FOUND'); }
        $hash = self::hash($this->combination($productId, $groupId, $prepared));
        $duplicate = $this->db->setQuery('SELECT id FROM #__dharmashop_products WHERE group_id=' . $groupId . ' AND combo_hash=' . $this->db->quote($hash) . ' AND id<>' . $productId, 0, 1)->loadResult();
        if ($duplicate) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_DUPLICATE'); }
        return $hash;
    }

    public function validateAxes(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || count($ids) > 6) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_AXES_REQUIRED'); }
        foreach ($ids as $id) {
            $f = $this->db->setQuery('SELECT target,type,store,level,state,params FROM #__dharmashop_fields WHERE id=' . $id)->loadObject();
            $settings = new Registry($f->params ?? '');
            if (!$f || $f->target !== 'product' || !in_array($f->type, ['list','color'], true) || $f->store !== 'option' || (int) $f->state !== 1 || (int) $f->level !== 1 || !(int) $settings->get('variation', 0) || (int) $settings->get('list_multiple', $settings->get('multiple', 0))) {
                throw new \RuntimeException('COM_DHARMASHOP_GROUP_AXIS_INVALID');
            }
        }
        return $ids;
    }

    private function editable(array $ids): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
        foreach ($ids as $id) {
            if (!$user->authorise('core.edit', 'com_dharmashop.product.' . $id)) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
        }
    }

    /** Caller owns transaction. Sorted group locks serialize competing member changes. */
    public function assign(array $ids, int $groupId, bool $allowMove = true): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || count($ids) > self::MEMBER_LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT'); }
        $this->editable($ids);
        $list = implode(',', $ids);
        $rows = $this->db->setQuery('SELECT id,group_id FROM #__dharmashop_products WHERE id IN (' . $list . ') ORDER BY id')->loadObjectList();
        if (count($rows) !== count($ids)) { throw new \RuntimeException('COM_DHARMASHOP_PRODUCT_NOT_FOUND'); }
        $groups = array_values(array_unique(array_filter(array_merge([$groupId], array_map(static fn($p) => (int) $p->group_id, $rows)))));
        sort($groups, SORT_NUMERIC);
        foreach ($groups as $group) {
            if (!$this->db->setQuery('SELECT id FROM #__dharmashop_product_groups WHERE id=' . $group . ' FOR UPDATE')->loadResult()) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_NOT_FOUND'); }
        }
        $locked = $this->db->setQuery('SELECT id,group_id FROM #__dharmashop_products WHERE id IN (' . $list . ') ORDER BY id FOR UPDATE')->loadObjectList();
        if ($rows != $locked) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_CHANGED'); }
        if (!$allowMove) {
            foreach ($locked as $product) {
                if ((int)$product->group_id > 0 && (int)$product->group_id !== $groupId) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_ALREADY_MEMBER'); }
            }
        }
        $hashes = []; $byId = [];
        if ($groupId > 0) {
            $count = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_products WHERE group_id=' . $groupId . ' AND id NOT IN (' . $list . ')')->loadResult();
            if ($count + count($ids) > self::MEMBER_LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT'); }
            foreach ($this->combinations($ids, $groupId) as $id => $values) {
                $hash = self::hash($values);
                $byId[$id] = $hash;
                if (isset($hashes[$hash])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_DUPLICATE'); }
                $hashes[$hash] = $id;
            }
            foreach ($hashes as $hash => $id) {
                if ($this->db->setQuery('SELECT id FROM #__dharmashop_products WHERE group_id=' . $groupId . ' AND combo_hash=' . $this->db->quote($hash) . ' AND id NOT IN (' . $list . ')', 0, 1)->loadResult()) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_DUPLICATE'); }
            }
        }
        foreach ($rows as $row) {
            if ((int) $row->group_id > 0 && (int) $row->group_id !== $groupId) { $this->materialize((int) $row->id); }
        }
        $this->db->setQuery('UPDATE #__dharmashop_products SET combo_hash=NULL WHERE id IN (' . $list . ')')->execute();
        foreach ($ids as $id) {
            $hash = $byId[$id] ?? null;
            $record = (object) ['id'=>$id, 'group_id'=>$groupId ?: null, 'combo_hash'=>$hash];
            $this->db->updateObject('#__dharmashop_products', $record, 'id', true);
        }
        foreach ($groups as $group) {
            $this->syncImages($group);
            $this->db->setQuery('UPDATE #__dharmashop_product_groups SET revision=revision+1 WHERE id=' . $group)->execute();
            $this->rebuild($group);
        }
    }

    /** Reconfigure only inside the group model transaction; checks all members before replacing axes. */
    public function configure(int $id, array $axes, array $members, array $media): void
    {
        $axes = $this->validateAxes($axes);
        if (!$this->db->setQuery('SELECT id FROM #__dharmashop_product_groups WHERE id=' . $id . ' FOR UPDATE')->loadResult()) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_NOT_FOUND'); }
        $old = array_map(static fn($p) => (int) $p->id, $this->members($id));
        $members = array_values(array_unique(array_filter(array_map('intval', $members))));
        $this->editable(array_values(array_unique(array_merge($old, $members))));
        if (count($members) > self::MEMBER_LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT'); }
        foreach (array_diff($old, $members) as $removed) { $this->assign([(int) $removed], 0); }
        $this->db->setQuery('UPDATE #__dharmashop_products SET combo_hash=NULL WHERE group_id=' . $id)->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_group_axes WHERE group_id=' . $id)->execute();
        foreach ($axes as $order => $axis) {
            $record = (object) ['group_id'=>$id,'field_id'=>$axis,'ordering'=>$order];
            $this->db->insertObject('#__dharmashop_group_axes', $record);
        }
        if ($members) { $this->assign($members, $id, false); }
        $this->db->setQuery('DELETE FROM #__dharmashop_group_media WHERE group_id=' . $id)->execute();
        foreach ($media as $order=>$row) {
            $record = (object) ['group_id'=>$id,'ordering'=>$order,'path'=>$row['path'],'alt'=>$row['alt'] ?? '', 'title'=>$row['title'] ?? ''];
            $this->db->insertObject('#__dharmashop_group_media', $record);
        }
        $first = $media[0] ?? null;
        $record = (object) ['id'=>$id,'cover'=>$first['path'] ?? null,'cover_alt'=>$first['alt'] ?? null];
        $this->db->updateObject('#__dharmashop_product_groups', $record, 'id', true);
        $this->syncImages($id);
        $this->db->setQuery('UPDATE #__dharmashop_product_groups SET revision=revision+1 WHERE id=' . $id)->execute();
        $this->rebuild($id);
        foreach ($members as $member) { (new SearchIndexer($this->db))->rebuild($member); }
    }

    /** Effective cover is cached for existing cards, cart and mail joins; own gallery remains intact. */
    private function syncImages(int $id): void
    {
        $this->db->setQuery("UPDATE #__dharmashop_products p INNER JOIN #__dharmashop_product_groups g ON g.id=p.group_id SET p.cover=CASE WHEN p.inherit_images='group' THEN g.cover ELSE NULL END,p.cover_alt=CASE WHEN p.inherit_images='group' THEN g.cover_alt ELSE NULL END WHERE p.group_id=" . $id . " AND p.inherit_images IN ('group','empty')")->execute();
    }

    public function rebuild(int $id): void
    {
        Sql::exec($this->db, 'UPDATE #__dharmashop_product_groups g LEFT JOIN (SELECT group_id,MIN(CASE WHEN state=1 THEN price_min END) mn,MAX(CASE WHEN state=1 THEN price_max END) mx,MAX(CASE WHEN state=1 THEN in_stock ELSE 0 END) ins,COUNT(*) cnt FROM #__dharmashop_products WHERE group_id=:id GROUP BY group_id) p ON p.group_id=g.id SET g.price_min=p.mn,g.price_max=p.mx,g.in_stock=COALESCE(p.ins,0),g.members_count=COALESCE(p.cnt,0) WHERE g.id=:target', [':id'=>[$id],':target'=>[$id]]);
    }

    public function refreshProducts(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return; }
        foreach ($this->db->setQuery('SELECT DISTINCT group_id FROM #__dharmashop_products WHERE id IN (' . implode(',', $ids) . ') AND group_id IS NOT NULL')->loadColumn() as $group) { $this->rebuild((int) $group); }
    }

    public function media(int $id): array
    {
        return $this->db->setQuery('SELECT path,alt,title FROM #__dharmashop_group_media WHERE group_id=' . $id . ' ORDER BY ordering')->loadAssocList();
    }

    /** Inheritance is resolved once; the group never inherits content from its children. */
    public function inherit(object $item): void
    {
        $group = $this->get((int) ($item->group_id ?? 0));
        if (!$group) { return; }
        foreach (['intro'=>'introtext','full'=>'fulltext'] as $mode=>$field) {
            $setting = $item->{'inherit_' . $mode} ?? 'own';
            if ($setting === 'group') { $item->$field = $group->$field; }
            elseif ($setting === 'empty') { $item->$field = ''; }
        }
        if (($item->inherit_images ?? 'own') === 'group') { $item->cover=$group->cover; $item->cover_alt=$group->cover_alt; }
        elseif (($item->inherit_images ?? 'own') === 'empty') { $item->cover=''; $item->cover_alt=''; }
    }

    private function materialize(int $id): void
    {
        $item=$this->db->setQuery('SELECT id,group_id,introtext,`fulltext`,cover,cover_alt,inherit_intro,inherit_full,inherit_images FROM #__dharmashop_products WHERE id=' . $id)->loadObject();
        $this->inherit($item);
        if ($item->inherit_images === 'group') {
            $gallery=new GalleryRepository($this->db);
            $gallery->save($id, $this->media((int)$item->group_id));
        } elseif ($item->inherit_images === 'empty') { (new GalleryRepository($this->db))->save($id, []); }
        unset($item->group_id);
        $item->inherit_intro=$item->inherit_full=$item->inherit_images='own';
        $this->db->updateObject('#__dharmashop_products',$item,'id',true);
        (new SearchIndexer($this->db))->rebuild($id);
    }

    /** Exact lookup, followed by publication/category ACL checks in the site controller. */
    public function resolve(int $id, array $values): int
    {
        $expected=array_map(static fn($a)=>(int)$a->id,$this->axes($id));
        $keys=array_map('intval',array_keys($values)); sort($keys); $sorted=$expected; sort($sorted);
        if ($keys !== $sorted) { return 0; }
        $hash=self::hash($values);
        $productId = (int)$this->db->setQuery('SELECT id FROM #__dharmashop_products WHERE group_id=' . $id . ' AND combo_hash=' . $this->db->quote($hash) . ' AND state=1')->loadResult();
        if (!$productId) { return 0; }
        $actual = $this->combination($productId, $id);
        ksort($actual, SORT_NUMERIC); ksort($values, SORT_NUMERIC);
        return $actual === array_map('intval', $values) ? $productId : 0;
    }
}
