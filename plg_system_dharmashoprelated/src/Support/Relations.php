<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopRelated\Support;

use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Индекс прямых назначений. Встречные связи вычисляются без изменения чужих товаров. */
final class Relations
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /** Источник истины — последнее сохранённое значение params, а не устаревший снимок формы. */
    public function syncProduct(int $id): void
    {
        if ($id <= 0) { return; }
        $db = $this->db;
        $db->transactionStart();
        try {
            $query = $db->createQuery()->select('params')->from($db->quoteName('#__dharmashop_products'))
                ->where('id = ' . $id);
            $json = $db->setQuery((string) $query . ' FOR UPDATE')->loadResult();
            $db->setQuery($db->createQuery()->delete($db->quoteName('#__dharmashop_related_products'))
                ->where('source_id = ' . $id))->execute();
            if ($json !== null) {
                $this->write($this->values($id, new Registry((string) $json)));
            }
            $db->transactionCommit();
        } catch (\Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
    }

    private function values(int $id, Registry $params): array
    {
        $db = $this->db;
        $values = [];
        foreach ($params->toArray() as $key => $rows) {
            if (!preg_match('/^related_(block_[a-f0-9]{16})$/D', (string) $key, $match)) { continue; }
            if (is_string($rows)) { $rows = json_decode($rows, true) ?: []; }
            $ids = Blocks::ids(array_map(static fn ($row): int => (int) (((array) $row)['product_id'] ?? 0), (array) $rows));
            foreach (array_slice($ids, 0, 100) as $target) {
                if ($target === $id) { continue; }
                $values[] = $id . ',' . $db->quote($match[1]) . ',' . $target;
            }
        }
        return $values;
    }

    private function write(array $values): void
    {
        foreach (array_chunk($values, 1000) as $chunk) {
            $query = $this->db->createQuery()->insert($this->db->quoteName('#__dharmashop_related_products'))
                ->columns($this->db->quoteName(['source_id', 'block_alias', 'target_id']))->values($chunk);
            $this->db->setQuery($query)->execute();
        }
    }

    /** Первичное заполнение нового индекса: курсор вместо большого OFFSET, только id/params. */
    public function rebuild(): void
    {
        $db = $this->db;
        $last = 0;
        do {
            $db->transactionStart();
            try {
                $query = $db->createQuery()->select('id, params')->from($db->quoteName('#__dharmashop_products'))
                    ->where('id > ' . $last)->order('id');
                $rows = $db->setQuery((string) $query . ' LIMIT 200 FOR UPDATE')->loadObjectList();
                $values = [];
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = (int) $row->id;
                    array_push($values, ...$this->values((int) $row->id, new Registry((string) $row->params)));
                    $last = (int) $row->id;
                }
                if ($ids) {
                    $db->setQuery($db->createQuery()->delete($db->quoteName('#__dharmashop_related_products'))
                        ->whereIn('source_id', $ids))->execute();
                    $this->write($values);
                }
                $db->transactionCommit();
            } catch (\Throwable $error) {
                $db->transactionRollback();
                throw $error;
            }
        } while (count($rows) === 200);
    }

    public function removeProduct(int $id): void
    {
        $db = $this->db;
        foreach (['source_id', 'target_id'] as $column) {
            $db->setQuery($db->createQuery()->delete($db->quoteName('#__dharmashop_related_products'))
                ->where($column . ' = ' . $id))->execute();
        }
    }

    /** Ограниченный список для нативной заметки редактора, без самоссылок и явных дублей. */
    public function incoming(int $id, string $alias, array $direct = []): array
    {
        $db = $this->db;
        $direct = Blocks::ids($direct);
        $query = $db->createQuery()->select('p.id, p.title')
            ->from($db->quoteName('#__dharmashop_related_products', 'r'))
            ->join('INNER', $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = r.source_id')
            ->where('r.block_alias = ' . $db->quote($alias))->where('r.target_id = ' . $id)
            ->where('p.id != ' . $id)->order('p.id');
        if ($direct) { $query->where('p.id NOT IN (' . implode(',', $direct) . ')'); }

        return $db->setQuery($query, 0, 21)->loadObjectList();
    }
}
