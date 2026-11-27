<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Защита тяжёлых запросов лимитом времени (MySQL hint и MariaDB session max_statement_time). */
final class QueryBudget
{
    /** Коды и сообщения прерванного по таймауту запроса (MySQL 3024, MariaDB 1969). */
    public static function isTimeout(\Throwable $error): bool
    {
        $message = $error->getMessage();

        return in_array((int) $error->getCode(), [3024, 1969], true)
            || str_contains($message, 'maximum statement execution time')
            || str_contains($message, 'max_statement_time exceeded');
    }

    /** Определяет MariaDB по версии сервера без дополнительных SQL-запросов (кэш на экземпляр БД). */
    public static function isMariaDb(DatabaseInterface $db): bool
    {
        static $engines = null;
        $engines ??= new \WeakMap();

        if (!isset($engines[$db])) {
            $engines[$db] = stripos($db->getVersion(), 'mariadb') !== false;
        }

        return $engines[$db];
    }

    /** Вычисляет бюджет времени в миллисекундах (обычный или защитный для списков/total). */
    public static function budgetMs(?int $milliseconds = null, bool $hard = false): int
    {
        $ms = max(50, min(10000, $milliseconds ?? (int) ComponentHelper::getParams('com_dharmashop')->get('filter_query_ms', 1500)));

        return $hard ? max(10000, 6 * $ms) : $ms;
    }

    /**
     * Protection for queries the page cannot do without (list, unfiltered total): a runaway limit, not the facet budget.
     * The tight `filter_query_ms` budget is meant for optional parts that can degrade; applying it to the list turned slow
     * queries into an exception page.
     */
    public static function hard(QueryInterface $query): QueryInterface
    {
        return self::apply($query, self::budgetMs(null, true));
    }

    public static function apply(QueryInterface $query, ?int $milliseconds = null): QueryInterface
    {
        $ms = self::budgetMs($milliseconds);
        $select = $query->select;
        if ($select === null) { return $query; }
        $columns = $select->getElements();
        if ($columns && !str_contains((string) $columns[0], 'MAX_EXECUTION_TIME')) {
            $columns[0] = '/*+ MAX_EXECUTION_TIME(' . $ms . ') */ ' . $columns[0];
            $query->clear('select')->select($columns);
        }
        return $query;
    }

    /**
     * Выполняет запрос в рамках бюджета времени: на MySQL добавляет SELECT-hint (0 лишних запросов),
     * на MariaDB временно задаёт SESSION max_statement_time с восстановлением (т.к. SET STATEMENT не поддерживается prepared statements).
     *
     * @template T
     * @param    callable(QueryInterface): T  $executor
     * @return   T
     */
    public static function run(DatabaseInterface $db, QueryInterface $query, callable $executor, ?int $milliseconds = null, bool $hard = false): mixed
    {
        $ms = self::budgetMs($milliseconds, $hard);

        if (!self::isMariaDb($db)) {
            return $executor(self::apply($query, $ms));
        }

        return self::withMariaDbTimeout($db, $ms, static fn (): mixed => $executor($query));
    }

    /**
     * Изолирует изменение `max_statement_time` на время одного вызова и гарантирует восстановление в `finally`.
     *
     * @template T
     * @param    callable(): T  $callback
     * @return   T
     */
    public static function withMariaDbTimeout(DatabaseInterface $db, int $milliseconds, callable $callback): mixed
    {
        static $baselines = null;
        static $current = null;
        $baselines ??= new \WeakMap();
        $current ??= new \WeakMap();

        if (!isset($baselines[$db])) {
            $baselines[$db] = (string) (float) $db->setQuery('SELECT @@SESSION.max_statement_time')->loadResult();
        }

        $previous = $current[$db] ?? $baselines[$db];
        $seconds = number_format(max(50, $milliseconds) / 1000, 3, '.', '');
        $failed = false;
        $db->setQuery('SET SESSION max_statement_time = ' . $seconds)->execute();
        $current[$db] = $seconds;

        try {
            return $callback();
        } catch (\Throwable $error) {
            $failed = true;
            throw $error;
        } finally {
            $current[$db] = $previous;

            try {
                $db->setQuery('SET SESSION max_statement_time = ' . $previous)->execute();
            } catch (\Throwable $restoreError) {
                if (!$failed) {
                    throw $restoreError;
                }
            }
        }
    }
}
