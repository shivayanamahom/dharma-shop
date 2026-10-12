<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Один SQL-состав для фасетов и списка. DISTINCT unit сохраняет совпадение условий на одном участнике. */
final class ScopedFacets
{
    private array $bindings = [];
    private float $deadline = 0;

    public function __construct(private readonly DatabaseInterface $db) {}

    /** $source возвращает запрос кандидатов с id, unit, price_min/max. */
    public function build(callable $source, FilterDefinition $definition, FilterState $state, string $mode, string $cacheKey, ?array $stored = null): array
    {
        $this->deadline = microtime(true) + max(50, min(10000, (int) ComponentHelper::getParams('com_dharmashop')->get('filter_query_ms', 1500))) / 1000;
        $base = fn(): array => FilterCache::remember('scoped:v3:' . $cacheKey, function () use ($source, $definition, $stored): array {
            try { return $this->aggregate($source(new FilterState()), $definition, $stored) + ['approximate' => false]; }
            catch (\Throwable $error) {
                if (!self::timeout($error)) { throw $error; }
                // Unknown availability must never become a false zero: keep choices usable and omit numbers.
                $fallback = ['options' => [], 'ranges' => [], 'price' => null, 'directories' => [], 'approximate' => true, 'unavailable' => true];
                foreach ($definition->fields as $field) { foreach ($field->options as $option) { $fallback['options'][$field->id][$option->id] = 1; } }
                foreach (['brand' => $definition->brands, 'badge' => $definition->badges, 'cat' => $definition->subcategories] as $key => $directory) { $fallback['directories'][$key] = array_fill_keys(array_keys($directory), 1); }
                return $fallback;
            }
        });
        if ($mode !== 'live' || $state->isEmpty()) { return $base() + ['counts' => $mode !== 'off']; }
        return FilterCache::remember('cascade:v3:' . $cacheKey . ':' . $state->key(), function () use ($source, $definition, $state, $base): array {
            $result = ['options' => [], 'ranges' => [], 'price' => null, 'directories' => [], 'approximate' => false, 'counts' => true];
            $fallbackFields = function (array $fields) use (&$result, $base): void {
                $fallback = $base(); $result['approximate'] = true;
                if (!empty($fallback['unavailable'])) { $result['unavailable'] = true; }
                foreach ($fields as $field) {
                    if ($field->kind === FilterField::OPTION) { $result['options'][$field->id] = $fallback['options'][$field->id] ?? []; }
                    elseif (isset($fallback['ranges'][$field->id])) { $result['ranges'][$field->id] = $fallback['ranges'][$field->id]; }
                }
            };
            $groups = [];
            foreach ($definition->fields as $field) {
                $own = isset($state->options[$field->id]) || isset($state->ranges[$field->id]);
                // Для AND добавление значения должно сохранять уже выбранные значения этого поля.
                if ($field->kind === FilterField::OPTION && $field->logic() === 'and') { $own = false; }
                $key = $own ? 'f' . $field->id : 'all';
                $groups[$key]['state'] = $own ? $state->withoutField($field->id) : $state;
                $groups[$key]['fields'][$field->id] = $field;
            }
            foreach ($groups as $group) {
                $query = $source($group['state']);
                if (!$this->withinLimit($query, $group['state']->key())) { $fallbackFields($group['fields']); continue; }
                try {
                    $subset = new FilterDefinition($definition->catid, $group['fields']);
                    $values = $this->values($query, $subset);
                    foreach ($group['fields'] as $field) {
                        if ($field->kind === FilterField::OPTION) { $result['options'][$field->id] = $values['options'][$field->id] ?? []; }
                        else { unset($result['ranges'][$field->id]); if (isset($values['ranges'][$field->id])) { $result['ranges'][$field->id] = $values['ranges'][$field->id]; } }
                    }
                } catch (\Throwable $error) { if (!self::timeout($error)) { throw $error; } $fallbackFields($group['fields']); }
            }
            foreach (['brand' => $definition->brands, 'badge' => $definition->badges, 'cat' => $definition->subcategories, 'price' => []] as $key => $directory) {
                $query = $source($state->withoutSystem($key));
                try {
                    if (!$this->withinLimit($query, $state->withoutSystem($key)->key())) { throw new \RuntimeException('Candidate limit', 3024); }
                    if ($key === 'price') { $result['price'] = $this->price($query); }
                    else { $result['directories'][$key] = $this->directory($query, array_keys($directory), $key !== 'cat'); }
                } catch (\Throwable $error) {
                    if (!self::timeout($error)) { throw $error; }
                    $fallback = $base(); $result['approximate'] = true;
                    if (!empty($fallback['unavailable'])) { $result['unavailable'] = true; }
                    if ($key === 'price') { $result['price'] = $fallback['price']; }
                    else { $result['directories'][$key] = $fallback['directories'][$key] ?? []; }
                }
            }
            return $result;
        });
    }

    private function aggregate(QueryInterface $source, FilterDefinition $definition, ?array $stored): array
    {
        $values = $stored ?? $this->values($source, $definition);
        $directories = ['brand' => [], 'badge' => [], 'cat' => []];
        $ids = array_unique(array_merge(array_keys($definition->brands), array_keys($definition->badges), array_keys($definition->subcategories)));
        if ($ids) {
            $query = $this->outer($source, ['fd.catid', 'COUNT(DISTINCT fc.unit) AS n'])
                ->join('INNER', '#__dharmashop_product_categories fd ON fd.product_id=fc.id')
                ->where('fd.catid IN (' . implode(',', array_map('intval', $ids)) . ')')->where('fd.state=1')->where($definition->subcategories ? '(fd.direct=1 OR fd.catid IN (' . implode(',', array_map('intval', array_keys($definition->subcategories))) . '))' : 'fd.direct=1')->group('fd.catid');
            foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
                $id = (int) $row->catid;
                if (isset($definition->brands[$id])) { $directories['brand'][$id] = (int) $row->n; }
                if (isset($definition->badges[$id])) { $directories['badge'][$id] = (int) $row->n; }
                if (isset($definition->subcategories[$id])) { $directories['cat'][$id] = (int) $row->n; }
            }
        }
        return $values + ['price' => $this->price($source), 'directories' => $directories];
    }

    private function outer(QueryInterface $source, array $columns): QueryInterface
    {
        $query = $this->db->createQuery()->select($columns)->from('(' . (string) $source . ') AS fc');
        foreach ($source->getBounded() as $name => $bound) {
            $this->bindings[$name] = $bound->value;
            $query->bind($name, $this->bindings[$name], $bound->dataType);
        }
        $remaining = (int) (($this->deadline - microtime(true)) * 1000);
        if ($remaining <= 0) { throw new \RuntimeException('Facet request time budget', 3024); }
        return QueryBudget::apply($query, $remaining);
    }

    /** @var array<string, bool> результат проверки по состоянию (ключ `FilterState::key()`): справочники без своего выбора считаются по тому же набору, что и поля */
    private array $within = [];

    private function withinLimit(QueryInterface $source, ?string $stateKey = null): bool
    {
        if ($stateKey !== null && isset($this->within[$stateKey])) {
            return $this->within[$stateKey];
        }

        $limit = max(100, min(100000, (int) ComponentHelper::getParams('com_dharmashop')->get('filter_live_limit', 20000)));
        $bounded = clone $source;
        $bounded->setLimit($limit + 1);
        try { $ok = (int) $this->db->setQuery($this->outer($bounded, ['COUNT(*)']))->loadResult() <= $limit; }
        catch (\Throwable $error) { if (!self::timeout($error)) { throw $error; } $ok = false; }

        if ($stateKey !== null) { $this->within[$stateKey] = $ok; }

        return $ok;
    }

    private function values(QueryInterface $source, FilterDefinition $definition): array
    {
        $result = ['options' => [], 'ranges' => []];
        if (!$definition->fields) { return $result; }
        $ids = implode(',', array_map('intval', array_keys($definition->fields)));
        $query = $this->outer($source, ['fv.field_id', 'fv.option_id', 'COUNT(DISTINCT fc.unit) AS n', 'MIN(fv.num) AS mn', 'MAX(COALESCE(fv.num_end,fv.num)) AS mx'])
            ->join('INNER', '#__dharmashop_field_values fv FORCE INDEX (idx_product) ON fv.product_id=fc.id')
            ->where('fv.field_id IN (' . $ids . ')')->group('fv.field_id,fv.option_id');
        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $field = (int) $row->field_id;
            if ($definition->fields[$field]->kind === FilterField::OPTION && $row->option_id !== null) { $result['options'][$field][(int) $row->option_id] = (int) $row->n; }
            elseif ($definition->fields[$field]->kind !== FilterField::OPTION && $row->mn !== null) { $result['ranges'][$field] = ['min' => (float) $row->mn, 'max' => (float) $row->mx]; }
        }
        return $result;
    }

    private function directory(QueryInterface $source, array $ids, bool $direct): array
    {
        if (!$ids) { return []; }
        $query = $this->outer($source, ['fd.catid', 'COUNT(DISTINCT fc.unit) AS n'])
            ->join('INNER', '#__dharmashop_product_categories fd ON fd.product_id=fc.id')
            ->where('fd.catid IN (' . implode(',', array_map('intval', $ids)) . ')')->where('fd.state=1')->group('fd.catid');
        if ($direct) { $query->where('fd.direct=1'); }
        $result = [];
        foreach ($this->db->setQuery($query)->loadObjectList() as $row) { $result[(int) $row->catid] = (int) $row->n; }
        return $result;
    }

    private function price(QueryInterface $source): ?array
    {
        $query = $this->outer($source, ['MIN(fc.price_min) AS mn', 'MAX(fc.price_max) AS mx'])->where('fc.price_min>0');
        $row = $this->db->setQuery($query)->loadObject();
        return $row && $row->mn !== null ? ['min' => (float) $row->mn, 'max' => (float) $row->mx] : null;
    }

    private static function timeout(\Throwable $error): bool
    {
        return in_array((int) $error->getCode(), [3024, 1969], true) || str_contains($error->getMessage(), 'maximum statement execution time');
    }
}
