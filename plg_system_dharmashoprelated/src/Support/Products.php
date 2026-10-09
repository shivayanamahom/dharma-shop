<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopRelated\Support;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
/** Ограниченный подбор опубликованных доступных товаров. Ручной порядок и индексируемые автоусловия. */
final class Products
{
    public function __construct(private readonly DatabaseInterface $db) {}
    public function find(object $current, array $block): array
    {
        $db = $this->db;
        $id = (int) $current->id;
        $levels = implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0]);
        $query = $db->createQuery()->select($db->quoteName(['p.id','p.catid','p.title','p.alias','p.cover','p.cover_alt',
            'p.introtext','p.price_min','p.price_max','p.price_zero_free','p.in_stock','p.unit','p.qty_min','p.qty_step','p.params']))
            ->select('c.title AS category_title')->from($db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where('p.state = 1')->where('p.id != ' . $id)->where("c.extension = 'com_dharmashop'")
            ->where('c.published = 1')->where('c.access IN (' . $levels . ')');
        $blocked = $db->createQuery()->select('blocked.id')->from($db->quoteName('#__categories', 'blocked'))
            ->where("blocked.extension = 'com_dharmashop'")->where('blocked.lft <= c.lft')->where('blocked.rgt >= c.rgt')
            ->where('(blocked.published != 1 OR blocked.access NOT IN (' . $levels . '))');
        $query->where('NOT EXISTS (' . $blocked . ')');
        if ($block['mode'] === 'manual') {
            $params = $current->params instanceof Registry ? $current->params : new Registry($current->params ?? '');
            $rows = $params->get('related_' . $block['alias'], []);
            if (is_string($rows)) { $rows = json_decode($rows, true) ?: []; }
            $ids = Blocks::ids(array_map(static fn ($row): int => (int) (((array) $row)['product_id'] ?? 0), (array) $rows));
            if ($ids) {
                $list = implode(',', $ids);
                $query->order('CASE WHEN p.id IN (' . $list . ') THEN 0 ELSE 1 END')
                    ->order('FIELD(p.id,' . $list . ')');
            }
            if (!empty($block['reciprocal'])) {
                $incoming = $db->createQuery()->select('r.source_id AS id')
                    ->from($db->quoteName('#__dharmashop_related_products', 'r'))
                    ->where('r.block_alias = ' . $db->quote($block['alias']))->where('r.target_id = ' . $id);
                $candidates = (string) $incoming;
                if ($ids) {
                    // Подзапрос вставляется строкой: его параметры должны принадлежать внешнему запросу.
                    $direct = $db->createQuery()->select('direct.id')->from($db->quoteName('#__dharmashop_products', 'direct'))
                        ->where('direct.id IN (' . implode(',', $query->bindArray($ids)) . ')');
                    $candidates .= ' UNION ' . $direct;
                }
                $query->join('INNER', '(' . $candidates . ') AS related ON related.id = p.id');
            } else {
                if (!$ids) { return []; }
                $query->whereIn('p.id', $ids);
            }
            $query->order('p.id');
        } elseif ($block['mode'] === 'categories') {
            $selection = Blocks::categories($block['categories']);
            $categories = Blocks::ids($selection);
            if (in_array('current', $selection, true)) { $categories[] = (int) ($current->catid ?? 0); }
            $categories = Blocks::ids($categories);
            if (!$categories) { return []; }
            // При преобразовании подзапроса в строку Joomla не переносит его привязки автоматически.
            $match = $db->createQuery()->select('pc.product_id')->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
                ->join('INNER', $db->quoteName('#__categories', 'cc') . ' ON cc.id = pc.catid')
                ->where('pc.catid IN (' . implode(',', $query->bindArray($categories)) . ')')->where('pc.state = 1')->where("cc.extension = 'com_dharmashop'")
                ->where('cc.published = 1')->where('cc.access IN (' . $levels . ')');
            $query->where('p.id IN (' . $match . ')')->order('p.id DESC');
        } else {
            $fieldIds = Blocks::ids($block['fields']);
            if (!$fieldIds) { return []; }
            $fields = $db->setQuery($db->createQuery()->select('f.id, f.store')->from($db->quoteName('#__dharmashop_fields', 'f'))
                ->whereIn('f.id', $fieldIds)->where("f.target = 'product'")->where('f.state = 1')
                ->where('f.access IN (' . $levels . ')')->where("f.store IN ('option','number','range','string')"))->loadObjectList();
            if (count($fields) !== count($fieldIds)) { return []; }
            $variant = $db->createQuery()->select('v.id')->from($db->quoteName('#__dharmashop_variants', 'v'))
                ->where('v.product_id = p.id')->where('v.state = 1');
            $anyField = ($block['fields_operator'] ?? 'and') === 'or';
            $seeds = [];
            $conditions = [];
            foreach ($fields as $field) {
                $equality = match ($field->store) {
                    'option' => 'candidate.option_id = source.option_id AND source.option_id IS NOT NULL',
                    'number' => 'candidate.num = source.num AND source.num IS NOT NULL',
                    'range' => 'candidate.num = source.num AND candidate.num_end <=> source.num_end AND source.num IS NOT NULL',
                    default => "candidate.str = source.str AND source.str IS NOT NULL AND source.str != ''",
                };
                $match = $db->createQuery()->select('candidate.id')->from($db->quoteName('#__dharmashop_field_values', 'candidate'))
                    ->join('INNER', $db->quoteName('#__dharmashop_field_values', 'source') . ' ON source.field_id = candidate.field_id AND ' . $equality)
                    ->where('candidate.field_id = ' . (int) $field->id)->where('candidate.product_id = p.id')
                    ->where('(candidate.variant_id IS NULL OR candidate.variant_id = v.id)')->where('source.product_id = ' . $id)
                    ->where('(source.variant_id IS NULL OR source.variant_id IN (SELECT sv.id FROM ' . $db->quoteName('#__dharmashop_variants', 'sv') . ' WHERE sv.product_id = ' . $id . ' AND sv.state = 1))');
                // И: достаточно кандидатов первого поля. ИЛИ: объединяем кандидатов всех полей по индексам значений.
                if (!$seeds || $anyField) {
                    $seeds[] = (string) $db->createQuery()->select('candidate.product_id')->from($db->quoteName('#__dharmashop_field_values', 'source'))
                        ->join('INNER', $db->quoteName('#__dharmashop_field_values', 'candidate') . ' ON candidate.field_id = source.field_id AND ' . $equality)
                        ->where('source.field_id = ' . (int) $field->id)->where('source.product_id = ' . $id)
                        ->where('(source.variant_id IS NULL OR source.variant_id IN (SELECT sv.id FROM ' . $db->quoteName('#__dharmashop_variants', 'sv') . ' WHERE sv.product_id = ' . $id . ' AND sv.state = 1))');
                }
                $conditions[] = 'EXISTS (' . $match . ')';
            }
            $variant->where('(' . implode($anyField ? ' OR ' : ' AND ', $conditions) . ')');
            $query->where('p.id IN (' . implode(' UNION ', $seeds) . ')')->where('EXISTS (' . $variant . ')')->order('p.id DESC');
        }
        $items = $db->setQuery($query, 0, $block['limit'])->loadObjectList();
        $rules = Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->orderRules();
        foreach ($items as $item) {
            $item->params = new Registry($item->params);
            $item->price_free = $rules->priceIsFree($item);
        }
        return $items;
    }
}
